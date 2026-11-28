<?php

namespace Tests\Feature\Llm;

use App\Enums\ResponseFormat;
use App\Llm\Dto\ChatRequest;
use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use App\Llm\OpenAiCompatibleClient;
use App\Llm\ProviderConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiCompatibleClientTest extends TestCase
{
    private function provider(): ProviderConfig
    {
        return new ProviderConfig(
            code: 'openrouter',
            name: 'OpenRouter',
            driver: 'openai',
            baseUrl: 'https://llm.test/v1',
            apiKey: 'secret-key',
            headers: ['X-Title' => 'AI Model Bench'],
        );
    }

    private function client(): OpenAiCompatibleClient
    {
        return $this->app->make(OpenAiCompatibleClient::class);
    }

    public function test_sends_request_and_parses_response(): void
    {
        Http::fake(['llm.test/v1/chat/completions' => Http::response($this->jsonFixture('chat_completion.json'))]);

        $response = $this->client()->chat($this->provider(), new ChatRequest(
            model: 'gemini-3.5-flash-lite',
            messages: [['role' => 'user', 'content' => 'article']],
            maxTokens: 300,
            responseFormat: ResponseFormat::JsonObject,
        ));

        $this->assertSame('{"score":7,"announcement":"Test"}', $response->content);
        $this->assertSame(120, $response->usage->promptTokens);
        $this->assertSame(15, $response->usage->completionTokens);
        $this->assertSame(200, $response->httpStatus);
        $this->assertSame('stop', $response->finishReason);
        $this->assertGreaterThanOrEqual(0, $response->latencyMs);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://llm.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && $request->hasHeader('X-Title', 'AI Model Bench')
            && $request['model'] === 'gemini-3.5-flash-lite'
            && $request['max_tokens'] === 300
            && $request['response_format'] === ['type' => 'json_object']);
    }

    public function test_rate_limit_throws_with_retry_after(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Too many requests']], 429, ['Retry-After' => '20'])]);

        try {
            $this->client()->chat($this->provider(), new ChatRequest('m', []));
            $this->fail('RateLimitedException expected');
        } catch (RateLimitedException $e) {
            $this->assertSame(20, $e->retryAfterSeconds);
            $this->assertSame(429, $e->httpStatus);
            $this->assertStringContainsString('Too many requests', $e->getMessage());
        }
    }

    public function test_server_error_throws_provider_exception(): void
    {
        Http::fake(['*' => Http::response('upstream down', 503)]);

        try {
            $this->client()->chat($this->provider(), new ChatRequest('m', []));
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertNotInstanceOf(RateLimitedException::class, $e);
            $this->assertSame(503, $e->httpStatus);
            $this->assertSame('upstream down', $e->responseBody);
        }
    }

    public function test_connection_error_throws_provider_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('timed out');

        $this->client()->chat($this->provider(), new ChatRequest('m', []));
    }

    public function test_unconfigured_provider_is_rejected_without_request(): void
    {
        Http::fake();
        $provider = new ProviderConfig('groq', 'Groq', 'openai', 'https://llm.test/v1', null);

        try {
            $this->client()->chat($provider, new ChatRequest('m', []));
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_api_key_is_not_leaked_into_error_message(): void
    {
        Http::fake(['*' => Http::response('bad key secret-key', 401)]);

        try {
            $this->client()->chat($this->provider(), new ChatRequest('m', []));
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertStringNotContainsString('secret-key', $e->getMessage());
            $this->assertStringNotContainsString('secret-key', (string) $e->responseBody);
        }
    }

    public function test_http_200_error_with_string_rate_limit_code_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'message' => 'slow down']])]);

        try {
            $this->client()->chat($this->provider(), new ChatRequest('m', []));
            $this->fail('RateLimitedException expected');
        } catch (RateLimitedException $e) {
            $this->assertSame(429, $e->httpStatus);
        }
    }

    public function test_http_200_error_with_unknown_code_maps_to_502(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'upstream_failed', 'message' => 'boom']])]);

        try {
            $this->client()->chat($this->provider(), new ChatRequest('m', []));
            $this->fail('ProviderException expected');
        } catch (ProviderException $e) {
            $this->assertNotInstanceOf(RateLimitedException::class, $e);
            $this->assertSame(502, $e->httpStatus);
        }
    }

    public function test_http_200_error_with_numeric_code_keeps_it(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 429, 'message' => 'limit']])]);

        $this->expectException(RateLimitedException::class);

        $this->client()->chat($this->provider(), new ChatRequest('m', []));
    }

    public function test_content_as_array_of_parts_is_joined(): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => [
                ['type' => 'text', 'text' => 'po'],
                ['type' => 'image_url', 'image_url' => ['url' => 'x']],
                ['type' => 'text', 'text' => 'ng'],
            ]], 'finish_reason' => 'stop']],
            'usage' => 'n/a',
        ])]);

        $response = $this->client()->chat($this->provider(), new ChatRequest('m', []));

        $this->assertSame('pong', $response->content);
        $this->assertNull($response->usage->promptTokens);
    }

    public function test_non_string_content_becomes_null(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 42]]]])]);

        $this->assertNull($this->client()->chat($this->provider(), new ChatRequest('m', []))->content);
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\LlmModel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Concerns\LoadsSchema;
use Tests\TestCase;

class ModelCommandsTest extends TestCase
{
    use LoadsSchema;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        $this->useTestProviders();
    }

    private function enabled(string $modelId): bool
    {
        return LlmModel::query()->where('model_id', $modelId)->firstOrFail()->enabled;
    }

    public function test_sync_prints_report(): void
    {
        $this->artisan('bench:models:sync')
            ->expectsOutputToContain('groq')
            ->expectsOutputToContain('GEMINI_API_KEY is not set')
            ->assertSuccessful();

        $this->assertSame(2, LlmModel::query()->count());
    }

    public function test_sync_discover(): void
    {
        Http::fake(['api.groq.com/openai/v1/models' => Http::response($this->jsonFixture('catalogs/groq.json'))]);

        $this->artisan('bench:models:sync', ['--provider' => 'groq', '--discover' => true])->assertSuccessful();

        $this->assertSame(2, LlmModel::query()->count());
    }

    public function test_sync_rejects_unknown_provider(): void
    {
        $this->artisan('bench:models:sync', ['--provider' => 'nope'])
            ->expectsOutputToContain('Unknown provider')
            ->assertFailed();
    }

    public function test_sync_fails_when_discovery_fails(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('denied', 401)]);

        $this->artisan('bench:models:sync', ['--provider' => 'groq', '--discover' => true])->assertFailed();
    }

    public function test_enable_and_disable_by_pattern(): void
    {
        $this->artisan('bench:models:sync')->assertSuccessful();

        $this->artisan('bench:models:enable', ['patterns' => ['groq:*'], '--disable' => true])
            ->expectsOutputToContain('Disabled 1 model')
            ->assertSuccessful();
        $this->assertFalse($this->enabled('qwen/qwen3-32b'));
        $this->assertTrue($this->enabled('gemini-3.5-flash-lite'));

        $this->artisan('bench:models:enable', ['patterns' => ['*qwen*']])->assertSuccessful();
        $this->assertTrue($this->enabled('qwen/qwen3-32b'));
    }

    public function test_enable_without_matches_fails(): void
    {
        $this->artisan('bench:models:sync')->assertSuccessful();

        $this->artisan('bench:models:enable', ['patterns' => ['groq:missing*']])->assertFailed();
    }

    public function test_list_shows_models(): void
    {
        $this->artisan('bench:models:sync')->assertSuccessful();

        $this->artisan('bench:models:list', ['--provider' => 'groq'])
            ->expectsOutputToContain('groq:qwen/qwen3-32b')
            ->assertSuccessful();
    }

    public function test_ping_enabled_models_of_configured_providers(): void
    {
        $this->useTestProviders(['groq' => ['models' => ['qwen/qwen3-32b' => [], 'llama-x' => []]]]);
        $this->artisan('bench:models:sync')->assertSuccessful();
        LlmModel::query()->where('model_id', 'gemini-3.5-flash-lite')->update(['enabled' => false]);

        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::sequence()
            ->push($this->jsonFixture('chat_completion.json'))
            ->push(['error' => ['message' => 'rate limit']], 429, ['Retry-After' => '7'])]);

        $this->artisan('bench:models:ping')
            ->expectsOutputToContain('ok')
            ->expectsOutputToContain('rate limited')
            ->assertFailed();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['max_tokens'] === 64);
        Sleep::assertSleptTimes(1);
    }

    public function test_ping_reports_unconfigured_provider_as_error(): void
    {
        $this->artisan('bench:models:sync')->assertSuccessful();
        Http::fake(['api.groq.com/*' => Http::response($this->jsonFixture('chat_completion.json'))]);

        $this->artisan('bench:models:ping')
            ->expectsOutputToContain('not configured')
            ->assertFailed();
    }

    public function test_ping_explicit_model_without_db(): void
    {
        Http::fake(['api.groq.com/openai/v1/chat/completions' => Http::response($this->jsonFixture('chat_completion.json'))]);

        $this->artisan('bench:models:ping', ['--model' => 'groq:some/model'])
            ->expectsOutputToContain('ok')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r['model'] === 'some/model');
    }

    public function test_ping_explicit_model_requires_provider(): void
    {
        $this->artisan('bench:models:ping', ['--model' => 'some/model'])
            ->expectsOutputToContain('provider:model')
            ->assertFailed();
    }

    public function test_ping_without_enabled_models_warns(): void
    {
        $this->artisan('bench:models:ping')
            ->expectsOutputToContain('bench:models:enable')
            ->assertFailed();
    }
}

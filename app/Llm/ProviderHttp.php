<?php

namespace App\Llm;

use App\Llm\Exceptions\ProviderException;
use App\Llm\Exceptions\RateLimitedException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Shared HTTP plumbing for provider calls: auth, timeouts, error mapping, secret masking.
 */
final class ProviderHttp
{
    private const BODY_LIMIT = 2000;

    private const MESSAGE_LIMIT = 300;

    public function __construct(
        private readonly Factory $http,
        private readonly RetryAfterResolver $retryAfter,
        private readonly Repository $config,
    ) {}

    /**
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws ProviderException
     */
    public function send(ProviderConfig $provider, callable $send): Response
    {
        if (! $provider->isConfigured()) {
            throw new ProviderException("Provider {$provider->code} is not configured: {$provider->missingReason()}");
        }

        try {
            $response = $send($this->pending($provider));
        } catch (ConnectionException $e) {
            throw new ProviderException($this->mask($provider, $e->getMessage()), previous: $e);
        }

        if ($response->failed()) {
            throw $this->failure($provider, $response->status(), $response->body(), $response->headers());
        }

        return $response;
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function failure(ProviderConfig $provider, int $status, string $body, array $headers = []): ProviderException
    {
        $body = mb_substr($this->mask($provider, $body), 0, self::BODY_LIMIT);
        $message = sprintf('%s HTTP %d: %s', $provider->code, $status, $this->errorMessage($body));

        return $status === 429
            ? new RateLimitedException($message, $this->retryAfter->resolve($headers, $body), $status, $body)
            : new ProviderException($message, $status, $body);
    }

    private function pending(ProviderConfig $provider): PendingRequest
    {
        return $this->http
            ->withToken((string) $provider->apiKey)
            ->withHeaders($provider->headers)
            ->acceptJson()
            ->timeout((int) $this->config->get('bench.http.timeout', 60))
            ->connectTimeout((int) $this->config->get('bench.http.connect_timeout', 10));
    }

    private function errorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data) && array_is_list($data)) {
            $data = $data[0] ?? [];
        }

        $message = is_array($data)
            ? (data_get($data, 'error.message') ?? data_get($data, 'errors.0.message') ?? data_get($data, 'error'))
            : null;

        return mb_substr(is_string($message) ? $message : $body, 0, self::MESSAGE_LIMIT);
    }

    private function mask(ProviderConfig $provider, string $text): string
    {
        return $provider->apiKey ? str_replace($provider->apiKey, '***', $text) : $text;
    }
}

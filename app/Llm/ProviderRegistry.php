<?php

namespace App\Llm;

use App\Llm\Dto\ModelInfo;
use App\Llm\Exceptions\UnknownProviderException;
use Illuminate\Contracts\Config\Repository;

/**
 * Builds ProviderConfig objects from config/bench.php `providers`.
 */
final class ProviderRegistry
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map('strval', array_keys($this->config->get('bench.providers', [])));
    }

    public function has(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /**
     * @return list<ProviderConfig>
     */
    public function all(): array
    {
        return array_map(fn (string $code) => $this->get($code), $this->codes());
    }

    /**
     * @throws UnknownProviderException
     */
    public function get(string $code): ProviderConfig
    {
        if (! $this->has($code)) {
            throw UnknownProviderException::forCode($code, $this->codes());
        }

        /** @var array<string, mixed> $settings */
        $settings = $this->config->get("bench.providers.{$code}");
        [$baseUrl, $accountReason] = $this->resolveBaseUrl($settings);
        $models = $settings['models'] ?? [];

        return new ProviderConfig(
            code: $code,
            name: (string) ($settings['name'] ?? $code),
            driver: (string) ($settings['driver'] ?? 'openai'),
            baseUrl: $baseUrl,
            apiKey: $this->stringOrNull($settings['api_key'] ?? null),
            catalog: $this->stringOrNull($settings['catalog'] ?? null),
            headers: $settings['headers'] ?? [],
            exclude: $settings['exclude'] ?? [],
            limits: $settings['limits'] ?? [],
            modelLimits: $settings['model_limits'] ?? [],
            models: $this->declaredModels($models),
            declaredModelLimits: array_filter(array_map(fn (array $m) => $m['limits'] ?? [], $models)),
            unconfiguredReason: $this->apiKeyReason($settings) ?? $accountReason,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function apiKeyReason(array $settings): ?string
    {
        return $this->stringOrNull($settings['api_key'] ?? null) === null
            ? ($settings['api_key_env'] ?? 'API key').' is not set'
            : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $models
     * @return list<ModelInfo>
     */
    private function declaredModels(array $models): array
    {
        $result = [];
        foreach ($models as $modelId => $options) {
            $result[] = new ModelInfo(
                modelId: (string) $modelId,
                displayName: $options['name'] ?? null,
                contextWindow: isset($options['context_window']) ? (int) $options['context_window'] : null,
                isFree: (bool) ($options['free'] ?? true),
                supportsJson: isset($options['json']) ? (bool) $options['json'] : null,
                enabled: (bool) ($options['enabled'] ?? true),
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{string, ?string} base url and the reason it is unusable
     */
    private function resolveBaseUrl(array $settings): array
    {
        $baseUrl = (string) ($settings['base_url'] ?? '');
        if (! str_contains($baseUrl, '{account_id}')) {
            return [$baseUrl, null];
        }

        $accountId = $this->stringOrNull($settings['account_id'] ?? null);
        if ($accountId === null) {
            return [$baseUrl, ($settings['account_id_env'] ?? 'account_id').' is not set'];
        }

        return [str_replace('{account_id}', rawurlencode($accountId), $baseUrl), null];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

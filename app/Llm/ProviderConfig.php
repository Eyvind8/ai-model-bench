<?php

namespace App\Llm;

use App\Llm\Dto\ModelInfo;

/**
 * Resolved settings of one provider from config/bench.php.
 */
final readonly class ProviderConfig
{
    /**
     * @param  string  $driver  client driver name (bench.drivers.clients)
     * @param  string|null  $catalog  catalog driver name (bench.drivers.catalogs), null = no discovery
     * @param  array<string, string>  $headers
     * @param  list<string>  $exclude  regexes of model IDs skipped during discovery
     * @param  array<string, int|null>  $limits  provider defaults
     * @param  array<string, array<string, int|null>>  $modelLimits  regex => limits override
     * @param  list<ModelInfo>  $models  models declared in config
     * @param  array<string, array<string, int|null>>  $declaredModelLimits  model ID => limits override
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $driver,
        public string $baseUrl,
        public ?string $apiKey,
        public ?string $catalog = null,
        public array $headers = [],
        public array $exclude = [],
        public array $limits = [],
        public array $modelLimits = [],
        public array $models = [],
        public array $declaredModelLimits = [],
        private ?string $unconfiguredReason = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->missingReason() === null;
    }

    public function missingReason(): ?string
    {
        if ($this->unconfiguredReason !== null) {
            return $this->unconfiguredReason;
        }

        return $this->apiKey === null || $this->apiKey === '' ? 'API key is not set' : null;
    }

    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    public function isExcluded(string $modelId): bool
    {
        foreach ($this->exclude as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Priority: per-model config > first matching regex override > provider defaults.
     *
     * @return array<string, int|null>
     */
    public function limitsFor(string $modelId): array
    {
        $limits = $this->limits;

        foreach ($this->modelLimits as $pattern => $override) {
            if (preg_match($pattern, $modelId) === 1) {
                $limits = array_merge($limits, $override);
                break;
            }
        }

        return array_merge($limits, $this->declaredModelLimits[$modelId] ?? []);
    }
}

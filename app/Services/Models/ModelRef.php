<?php

namespace App\Services\Models;

/**
 * "provider:model" reference. Model IDs may contain ":" themselves
 * (e.g. "openrouter:qwen/qwen3:free"), so only a known provider prefix is split off.
 */
final readonly class ModelRef
{
    public function __construct(
        public ?string $provider,
        public string $model,
    ) {}

    /**
     * @param  list<string>  $knownProviders
     */
    public static function parse(string $ref, array $knownProviders): self
    {
        $ref = trim($ref);
        $separator = strpos($ref, ':');

        if ($separator !== false && in_array(substr($ref, 0, $separator), $knownProviders, true)) {
            return new self(substr($ref, 0, $separator), substr($ref, $separator + 1));
        }

        return new self(null, $ref);
    }
}

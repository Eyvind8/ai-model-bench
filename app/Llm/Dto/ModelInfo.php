<?php

namespace App\Llm\Dto;

/**
 * Model description: declared in config or returned by a provider catalog.
 */
final readonly class ModelInfo
{
    /**
     * @param  bool  $enabled  initial enabled state when the model is first stored
     */
    public function __construct(
        public string $modelId,
        public ?string $displayName = null,
        public ?int $contextWindow = null,
        public bool $isFree = true,
        public ?bool $supportsJson = null,
        public bool $enabled = false,
    ) {}
}

<?php

namespace App\Llm\Dto;

final readonly class Usage
{
    public function __construct(
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $usage
     */
    public static function fromArray(?array $usage): self
    {
        return new self(
            isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
        );
    }
}

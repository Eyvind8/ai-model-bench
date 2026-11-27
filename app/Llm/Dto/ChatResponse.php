<?php

namespace App\Llm\Dto;

final readonly class ChatResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $content,
        public Usage $usage,
        public int $latencyMs,
        public int $httpStatus,
        public ?string $finishReason,
        public array $raw,
    ) {}
}

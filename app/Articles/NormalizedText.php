<?php

namespace App\Articles;

final readonly class NormalizedText
{
    public function __construct(
        public string $text,
        public int $originalChars,
        public bool $truncated,
    ) {}
}

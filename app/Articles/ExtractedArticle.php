<?php

namespace App\Articles;

final readonly class ExtractedArticle
{
    public function __construct(
        public ?string $title,
        public string $text,
        public ?string $author = null,
        public ?string $publishedAt = null,
        public ?string $lang = null,
    ) {}
}

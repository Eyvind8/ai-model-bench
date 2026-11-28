<?php

namespace App\Contracts;

use App\Articles\ArticleFetchException;
use App\Articles\ExtractedArticle;

interface ArticleExtractorInterface
{
    /**
     * @throws ArticleFetchException
     */
    public function extract(string $html, string $url): ExtractedArticle;
}

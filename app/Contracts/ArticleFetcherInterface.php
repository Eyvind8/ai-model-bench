<?php

namespace App\Contracts;

use App\Articles\ArticleFetchException;
use App\Articles\FetchedPage;

interface ArticleFetcherInterface
{
    /**
     * @throws ArticleFetchException
     */
    public function fetch(string $url): FetchedPage;
}

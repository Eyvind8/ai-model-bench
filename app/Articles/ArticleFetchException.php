<?php

namespace App\Articles;

use RuntimeException;

/**
 * Article could not be downloaded or extracted; message is shown to the user as the reason.
 */
class ArticleFetchException extends RuntimeException {}

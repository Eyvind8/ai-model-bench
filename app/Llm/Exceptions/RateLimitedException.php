<?php

namespace App\Llm\Exceptions;

use Throwable;

class RateLimitedException extends ProviderException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?int $httpStatus = 429,
        ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $responseBody, $previous);
    }
}

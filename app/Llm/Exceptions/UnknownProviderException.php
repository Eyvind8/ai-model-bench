<?php

namespace App\Llm\Exceptions;

use InvalidArgumentException;

class UnknownProviderException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known
     */
    public static function forCode(string $code, array $known): self
    {
        return new self(sprintf('Unknown provider "%s". Configured: %s', $code, implode(', ', $known)));
    }
}

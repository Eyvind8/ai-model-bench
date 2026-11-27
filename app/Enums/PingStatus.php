<?php

namespace App\Enums;

enum PingStatus: string
{
    case Ok = 'ok';
    case RateLimited = 'rate_limited';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'ok',
            self::RateLimited => 'rate limited',
            self::Error => 'error',
        };
    }
}

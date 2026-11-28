<?php

namespace App\Enums;

enum ResultStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Error = 'error';
    case RateLimited = 'rate_limited';
}

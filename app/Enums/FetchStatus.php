<?php

namespace App\Enums;

enum FetchStatus: string
{
    case Pending = 'pending';
    case Ok = 'ok';
    case Failed = 'failed';
    case Manual = 'manual';
}

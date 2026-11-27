<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Ok = 'ok';
    case Skipped = 'skipped';
    case Failed = 'failed';
}

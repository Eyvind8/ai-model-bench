<?php

namespace App\Services\Models;

use App\Enums\SyncStatus;

final readonly class SyncReport
{
    public function __construct(
        public string $provider,
        public SyncStatus $status,
        public SyncCounts $counts = new SyncCounts,
        public ?string $message = null,
    ) {}
}

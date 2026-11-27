<?php

namespace App\Services\Ping;

use App\Enums\PingStatus;

final readonly class PingResult
{
    public function __construct(
        public PingTarget $target,
        public PingStatus $status,
        public ?int $latencyMs = null,
        public ?string $reply = null,
        public ?string $error = null,
    ) {}
}

<?php

namespace App\Services\Ping;

use App\Llm\ProviderConfig;

final readonly class PingTarget
{
    public function __construct(
        public ProviderConfig $provider,
        public string $modelId,
    ) {}
}

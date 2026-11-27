<?php

namespace App\Services\Models;

final readonly class SyncCounts
{
    public function __construct(
        public int $added = 0,
        public int $updated = 0,
        public int $removed = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            $this->added + $other->added,
            $this->updated + $other->updated,
            $this->removed + $other->removed,
        );
    }
}

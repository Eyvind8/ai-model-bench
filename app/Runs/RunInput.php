<?php

namespace App\Runs;

use App\Models\DatasetItem;

/**
 * Text a model receives: a dataset item or an ad-hoc input.
 */
final readonly class RunInput
{
    public function __construct(
        public string $text,
        public ?DatasetItem $item = null,
    ) {}

    public static function fromItem(DatasetItem $item): self
    {
        return new self($item->input, $item);
    }
}

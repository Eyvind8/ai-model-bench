<?php

namespace App\Datasets;

use App\Enums\FetchStatus;
use App\Models\Dataset;
use App\Models\DatasetItem;

/**
 * Manual edits of dataset items: add, fix text of failed downloads, reference labels.
 */
final class DatasetItemEditor
{
    /**
     * @param  array{title?: ?string, input: string, expected_score?: ?int, expected_announcement?: ?string}  $data
     */
    public function add(Dataset $dataset, array $data): DatasetItem
    {
        return $dataset->items()->create($this->attributes($data));
    }

    /**
     * Text typed by a person over a failed/pending download becomes a manual item.
     *
     * @param  array{title?: ?string, input: string, expected_score?: ?int, expected_announcement?: ?string}  $data
     */
    public function update(DatasetItem $item, array $data): void
    {
        $attributes = $this->attributes($data);

        if ($item->fetch_status !== null && $attributes['input'] !== $item->input) {
            $attributes += ['fetch_status' => FetchStatus::Manual, 'fetch_error' => null];
        }

        $item->update($attributes);
    }

    public function label(DatasetItem $item, int $score, ?string $announcement): void
    {
        $item->update(['expected_score' => $score, 'expected_announcement' => $this->nullIfBlank($announcement)]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => $this->nullIfBlank($data['title'] ?? null),
            'input' => trim((string) $data['input']),
            'expected_score' => isset($data['expected_score']) && $data['expected_score'] !== '' ? (int) $data['expected_score'] : null,
            'expected_announcement' => $this->nullIfBlank($data['expected_announcement'] ?? null),
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

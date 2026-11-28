<?php

namespace App\Datasets;

use App\Articles\ArticleFetchException;
use App\Articles\ArticleImporter;
use App\Models\Dataset;

/**
 * Imports rows into a dataset. Rows with text are stored as is, rows with only a URL are queued for download.
 *
 * Recognized columns: title, input|text|content, url|source_url, expected_score|score,
 * expected_announcement|announcement; everything else goes to meta.
 */
final class DatasetImporter
{
    private const ALIASES = [
        'input' => ['input', 'text', 'content'],
        'url' => ['url', 'source_url'],
        'expected_score' => ['expected_score', 'score'],
        'expected_announcement' => ['expected_announcement', 'announcement'],
        'title' => ['title'],
    ];

    public function __construct(
        private readonly DatasetFileReader $reader,
        private readonly ArticleImporter $articles,
    ) {}

    public function importFile(Dataset $dataset, string $path): DatasetImportReport
    {
        $added = $queued = $skipped = 0;
        $errors = [];

        foreach ($this->reader->read($path) as $index => $row) {
            $line = $index + 1;
            $fields = $this->fields($row);
            $score = $this->score($fields['expected_score'], $line, $errors);

            if ($fields['input'] !== '') {
                $dataset->items()->create($this->itemAttributes($fields, $score, $row));
                $added++;
            } elseif ($fields['url'] !== '') {
                $this->queueUrl($dataset, $fields, $score, $line, $errors) ? $queued++ : $skipped++;
            } else {
                $errors[] = "row {$line}: no input text and no url";
                $skipped++;
            }
        }

        return new DatasetImportReport($added, $queued, $skipped, $errors);
    }

    /**
     * @param  array<string, string>  $fields
     * @param  list<string>  $errors
     */
    private function queueUrl(Dataset $dataset, array $fields, ?int $score, int $line, array &$errors): bool
    {
        try {
            $item = $this->articles->queue($dataset, $fields['url'], $score, $fields['title'] ?: null);
        } catch (ArticleFetchException $e) {
            $errors[] = "row {$line}: {$e->getMessage()}";

            return false;
        }

        if ($item === null) {
            $errors[] = "row {$line}: duplicate url";
        }

        return $item !== null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function fields(array $row): array
    {
        $row = array_change_key_case($row);
        $fields = [];
        foreach (self::ALIASES as $field => $keys) {
            $value = '';
            foreach ($keys as $key) {
                if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                    $value = trim((string) $row[$key]);
                    break;
                }
            }
            $fields[$field] = $value;
        }

        return $fields;
    }

    /**
     * @param  list<string>  $errors
     */
    private function score(string $value, int $line, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }
        if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 10) {
            $errors[] = "row {$line}: expected_score must be 1-10, got \"{$value}\"";

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function itemAttributes(array $fields, ?int $score, array $row): array
    {
        $known = array_merge(...array_values(self::ALIASES));
        $meta = array_diff_key(array_change_key_case($row), array_flip($known));

        return [
            'title' => $fields['title'] ?: null,
            'input' => $fields['input'],
            'source_url' => $fields['url'] ?: null,
            'expected_score' => $score,
            'expected_announcement' => $fields['expected_announcement'] ?: null,
            'meta_json' => array_filter($meta, fn ($v) => $v !== null && $v !== '') ?: null,
        ];
    }
}

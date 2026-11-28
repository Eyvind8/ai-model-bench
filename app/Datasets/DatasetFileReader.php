<?php

namespace App\Datasets;

use InvalidArgumentException;
use JsonException;

/**
 * Reads dataset rows from CSV (header row), JSON (array or {"items": [...]}) or JSONL.
 */
final class DatasetFileReader
{
    /**
     * @param  string|null  $format  csv|json|jsonl, default: file extension (uploads have none)
     * @return list<array<string, mixed>>
     */
    public function read(string $path, ?string $format = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("File not found: {$path}");
        }

        return match (strtolower($format ?? pathinfo($path, PATHINFO_EXTENSION))) {
            'csv' => $this->csv($path),
            'json' => $this->json((string) file_get_contents($path)),
            'jsonl', 'ndjson' => $this->jsonl((string) file_get_contents($path)),
            default => throw new InvalidArgumentException('Supported formats: csv, json, jsonl'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function json(string $content): array
    {
        $data = $this->decode($content);
        $rows = array_is_list($data) ? $data : ($data['items'] ?? null);

        if (! is_array($rows)) {
            throw new InvalidArgumentException('JSON must be an array of rows or {"items": [...]}');
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonl(string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)), fn (string $l) => $l !== '');

        return array_values(array_map(fn (string $line) => $this->decode($line), $lines));
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON: '.$e->getMessage(), previous: $e);
        }

        return is_array($data) ? $data : throw new InvalidArgumentException('JSON row must be an object');
    }
}

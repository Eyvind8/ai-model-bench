<?php

namespace App\Runs;

use App\Models\DatasetItem;

/**
 * Fills scenario templates: {{input}}, {{title}}, {{url}}, {{meta.<key>}}. Unknown placeholders become empty.
 */
final class PromptRenderer
{
    public function render(string $template, RunInput $input): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            fn (array $m) => $this->value($m[1], $input),
            $template,
        );
    }

    private function value(string $name, RunInput $input): string
    {
        $item = $input->item;

        return match (true) {
            $name === 'input' => $input->text,
            $name === 'title' => (string) $item?->title,
            $name === 'url' => (string) $item?->source_url,
            str_starts_with($name, 'meta.') => $this->meta($item, substr($name, 5)),
            default => '',
        };
    }

    private function meta(?DatasetItem $item, string $key): string
    {
        $value = $item?->meta_json[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}

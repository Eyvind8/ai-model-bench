<?php

namespace App\Runs\Execution;

/**
 * Extracts the JSON object from a model reply: strips <think> blocks, ```json fences
 * and text around the first balanced {...}.
 */
final class JsonResponseParser
{
    /**
     * @return string|null JSON text that decodes to an object, null when there is none
     */
    public function extract(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $content = trim((string) preg_replace('#<think>.*?</think>#s', '', $content));
        if ($this->isObject($content)) {
            return $content;
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m) === 1 && $this->isObject(trim($m[1]))) {
            return trim($m[1]);
        }

        $candidate = $this->firstBalancedObject($content);

        return $candidate !== null && $this->isObject($candidate) ? $candidate : null;
    }

    private function isObject(string $json): bool
    {
        return str_starts_with($json, '{') && is_array(json_decode($json, true));
    }

    private function firstBalancedObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = $escaped = false;
        for ($i = $start, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($char === '"') {
                $inString = true;

                continue;
            }
            $depth += match ($char) {
                '{' => 1, '}' => -1, default => 0
            };
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }

        return null;
    }
}

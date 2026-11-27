<?php

namespace App\Llm;

/**
 * Extracts "retry after N seconds" from a 429 response (headers or body).
 */
final class RetryAfterResolver
{
    private const RESET_HEADERS = ['x-ratelimit-reset-requests', 'x-ratelimit-reset-tokens'];

    private const DURATION_PATTERN = '/^(?:(\d+)h)?(?:(\d+)m(?!s))?(?:([\d.]+)s)?(?:([\d.]+)ms)?$/';

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function resolve(array $headers, string $body): ?int
    {
        $headers = $this->normalize($headers);

        if (isset($headers['retry-after']) && is_numeric($headers['retry-after'])) {
            return $this->toSeconds((float) $headers['retry-after']);
        }

        $resets = array_filter(array_map(
            fn (string $name) => isset($headers[$name]) ? $this->parseDuration($headers[$name]) : null,
            self::RESET_HEADERS,
        ));
        if ($resets !== []) {
            return max($resets);
        }

        if (preg_match('/"retryDelay"\s*:\s*"([\d.]+)s"/', $body, $m) === 1) {
            return $this->toSeconds((float) $m[1]);
        }

        return null;
    }

    /**
     * Parses durations like "2m59.56s", "7.66s", "250ms", "1h2m".
     */
    private function parseDuration(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || preg_match(self::DURATION_PATTERN, $value, $m) !== 1) {
            return null;
        }

        return $this->toSeconds(
            (int) ($m[1] ?? 0) * 3600
            + (int) ($m[2] ?? 0) * 60
            + (float) ($m[3] ?? 0)
            + (float) ($m[4] ?? 0) / 1000
        );
    }

    private function toSeconds(float $seconds): int
    {
        return max(1, (int) ceil($seconds));
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>
     */
    private function normalize(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[strtolower($name)] = is_array($value) ? (string) ($value[0] ?? '') : $value;
        }

        return $result;
    }
}

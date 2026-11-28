<?php

namespace App\Llm;

/**
 * Normalizes OpenAI-compatible /chat/completions bodies, which differ slightly between providers.
 */
final class ChatCompletionMapper
{
    private const RATE_LIMIT_CODES = ['rate_limit_exceeded', 'rate_limited', 'too_many_requests', 'resource_exhausted'];

    private const FALLBACK_ERROR_STATUS = 502;

    /**
     * HTTP status of an error reported inside a 200 body (OpenRouter), null when there is none.
     *
     * @param  array<string, mixed>  $data
     */
    public function embeddedErrorStatus(array $data): ?int
    {
        if (! isset($data['error']) || isset($data['choices'])) {
            return null;
        }

        $code = data_get($data, 'error.code');

        return match (true) {
            is_numeric($code) && (int) $code >= 400 && (int) $code <= 599 => (int) $code,
            is_string($code) && in_array(strtolower($code), self::RATE_LIMIT_CODES, true) => 429,
            default => self::FALLBACK_ERROR_STATUS,
        };
    }

    /**
     * String content, or text parts joined when content is an array of parts.
     *
     * @param  array<string, mixed>  $data
     */
    public function content(array $data): ?string
    {
        $content = data_get($data, 'choices.0.message.content');

        if (is_string($content)) {
            return $content;
        }
        if (! is_array($content)) {
            return null;
        }

        $text = array_map(
            fn (mixed $part) => is_array($part) && ($part['type'] ?? null) === 'text' ? (string) ($part['text'] ?? '') : '',
            $content,
        );

        return implode('', $text);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function usage(array $data): ?array
    {
        return isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function finishReason(array $data): ?string
    {
        $reason = data_get($data, 'choices.0.finish_reason');

        return is_string($reason) ? $reason : null;
    }
}

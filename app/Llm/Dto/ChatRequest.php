<?php

namespace App\Llm\Dto;

use App\Enums\ResponseFormat;

final readonly class ChatRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $jsonSchema
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ResponseFormat $responseFormat = ResponseFormat::Text,
        public ?array $jsonSchema = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = ['model' => $this->model, 'messages' => $this->messages];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }
        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        $format = $this->responseFormatPayload();
        if ($format !== null) {
            $payload['response_format'] = $format;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function responseFormatPayload(): ?array
    {
        return match (true) {
            $this->responseFormat === ResponseFormat::Text => null,
            $this->responseFormat === ResponseFormat::JsonSchema && $this->jsonSchema !== null => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'response', 'strict' => true, 'schema' => $this->jsonSchema],
            ],
            default => ['type' => 'json_object'],
        };
    }
}

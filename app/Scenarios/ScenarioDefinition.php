<?php

namespace App\Scenarios;

use App\Enums\ResponseFormat;

/**
 * Immutable scenario description. Also used as the run snapshot (toArray/fromArray).
 */
final readonly class ScenarioDefinition
{
    /**
     * @param  array<string, mixed>|null  $jsonSchema
     * @param  array{temperature?: float, max_tokens?: int, top_p?: float}  $params
     * @param  array<string, array<string, mixed>>  $metrics  label => ['metric' => name, ...options]
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $systemPrompt,
        public string $userTemplate,
        public ResponseFormat $responseFormat = ResponseFormat::Text,
        public ?array $jsonSchema = null,
        public array $params = [],
        public array $metrics = [],
        public ?string $judgeRubric = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $slug, array $data): self
    {
        return new self(
            slug: $slug,
            name: (string) ($data['name'] ?? $slug),
            systemPrompt: trim((string) ($data['system_prompt'] ?? '')),
            userTemplate: (string) ($data['user_template'] ?? '{{input}}'),
            responseFormat: ResponseFormat::tryFrom((string) ($data['response_format'] ?? 'text')) ?? ResponseFormat::Text,
            jsonSchema: $data['json_schema'] ?? null,
            params: $data['params'] ?? [],
            metrics: $data['metrics'] ?? [],
            judgeRubric: $data['judge_rubric'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'system_prompt' => $this->systemPrompt,
            'user_template' => $this->userTemplate,
            'response_format' => $this->responseFormat->value,
            'json_schema' => $this->jsonSchema,
            'params' => $this->params,
            'metrics' => $this->metrics,
            'judge_rubric' => $this->judgeRubric,
        ];
    }

    public function expectsJson(): bool
    {
        return $this->responseFormat !== ResponseFormat::Text;
    }
}

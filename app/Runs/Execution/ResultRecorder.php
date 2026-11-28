<?php

namespace App\Runs\Execution;

use App\Enums\JsonMode;
use App\Enums\ResultStatus;
use App\Llm\Dto\ChatResponse;
use App\Metrics\MetricEvaluator;
use App\Models\Result;
use App\Scenarios\ScenarioDefinition;

/**
 * Stores the final state of a result: parsed answer, validity flags, auto metrics, or the error.
 */
final class ResultRecorder
{
    public function __construct(
        private readonly JsonResponseParser $parser,
        private readonly JsonSchemaValidator $validator,
        private readonly MetricEvaluator $metrics,
    ) {}

    public function success(Result $result, ChatResponse $response, ScenarioDefinition $scenario): void
    {
        $parsed = $this->parse($result, $response->content, $scenario);

        $result->update([
            'status' => ResultStatus::Ok,
            'raw_response' => $response->raw,
            'content' => $response->content,
            'latency_ms' => $response->latencyMs,
            'prompt_tokens' => $response->usage->promptTokens,
            'completion_tokens' => $response->usage->completionTokens,
            'http_status' => $response->httpStatus,
            ...$parsed,
        ]);

        $this->metrics->evaluate($result, $scenario);
    }

    public function failure(Result $result, ResultStatus $status, string $message, ?int $httpStatus = null): void
    {
        $result->update(['status' => $status, 'error_message' => $message, 'http_status' => $httpStatus]);
    }

    /**
     * @return array{parsed_json: ?array<string, mixed>, json_valid: ?bool, schema_valid: ?bool, error_message: ?string}
     */
    private function parse(Result $result, ?string $content, ScenarioDefinition $scenario): array
    {
        if ($result->json_mode === JsonMode::None || $result->json_mode === null) {
            return ['parsed_json' => null, 'json_valid' => null, 'schema_valid' => null, 'error_message' => null];
        }

        $json = $this->parser->extract($content);
        $errors = $json !== null && $scenario->jsonSchema !== null ? $this->validator->validate($json, $scenario->jsonSchema) : [];

        return [
            'parsed_json' => $json !== null ? json_decode($json, true) : null,
            'json_valid' => $json !== null,
            'schema_valid' => $json !== null && $scenario->jsonSchema !== null ? $errors === [] : null,
            'error_message' => match (true) {
                $json === null => 'no JSON object in reply',
                $errors !== [] => 'schema: '.implode('; ', array_slice($errors, 0, 5)),
                default => null,
            },
        ];
    }
}

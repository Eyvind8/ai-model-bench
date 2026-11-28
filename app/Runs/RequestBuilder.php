<?php

namespace App\Runs;

use App\Enums\JsonMode;
use App\Enums\ResponseFormat;
use App\Llm\Dto\ChatRequest;
use App\Models\LlmModel;
use App\Scenarios\ScenarioDefinition;

/**
 * Builds the chat request for one model and input. Models known not to support
 * response_format get a plain request (JSON is then extracted from text: fallback mode).
 */
final class RequestBuilder
{
    public function __construct(private readonly PromptRenderer $renderer) {}

    /**
     * @return array{ChatRequest, JsonMode}
     */
    public function build(ScenarioDefinition $scenario, LlmModel $model, RunInput $input): array
    {
        $messages = array_values(array_filter([
            $scenario->systemPrompt !== '' ? ['role' => 'system', 'content' => $scenario->systemPrompt] : null,
            ['role' => 'user', 'content' => $this->renderer->render($scenario->userTemplate, $input)],
        ]));
        $mode = $this->jsonMode($scenario, $model);

        $request = new ChatRequest(
            model: $model->model_id,
            messages: $messages,
            temperature: isset($scenario->params['temperature']) ? (float) $scenario->params['temperature'] : null,
            maxTokens: isset($scenario->params['max_tokens']) ? (int) $scenario->params['max_tokens'] : null,
            responseFormat: $mode === JsonMode::Native ? $scenario->responseFormat : ResponseFormat::Text,
            jsonSchema: $scenario->jsonSchema,
        );

        return [$request, $mode];
    }

    private function jsonMode(ScenarioDefinition $scenario, LlmModel $model): JsonMode
    {
        return match (true) {
            ! $scenario->expectsJson() => JsonMode::None,
            $model->supports_json === false => JsonMode::Fallback,
            default => JsonMode::Native,
        };
    }
}

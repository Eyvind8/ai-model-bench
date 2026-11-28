<?php

namespace App\Evaluation\Judge;

use App\Enums\ResponseFormat;
use App\Llm\Dto\ChatRequest;
use App\Models\LlmModel;
use App\Models\Result;
use App\Scenarios\ScenarioDefinition;

/**
 * Judge request: the exact messages the model received + its answer, never the model name.
 */
final class JudgePromptBuilder
{
    private const MAX_MESSAGE_CHARS = 8000;

    private const MAX_TOKENS = 400;

    public function build(ScenarioDefinition $scenario, Result $result, LlmModel $judge): ChatRequest
    {
        $criteria = JudgeCriteria::fromRubric($scenario->judgeRubric);
        $keys = implode(', ', array_map(fn (string $k) => "\"{$k}\": <1-10>", array_keys($criteria)));

        $system = "You are a strict evaluator. You will see the messages an AI model received and its answer.\n"
            .'Rate the answer on each criterion from 1 (bad) to 10 (excellent): '.implode('; ', $criteria).".\n"
            ."Then give an overall score from 1 to 10.\n"
            ."Respond strictly with JSON: {\"scores\": {{$keys}}, \"overall\": <1-10>, \"comment\": \"<one sentence>\"}";

        return new ChatRequest(
            model: $judge->model_id,
            messages: [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $this->transcript($result)]],
            temperature: 0.0,
            maxTokens: self::MAX_TOKENS,
            responseFormat: $judge->supports_json === false ? ResponseFormat::Text : ResponseFormat::JsonObject,
        );
    }

    private function transcript(Result $result): string
    {
        $parts = [];
        foreach ($result->request_json['messages'] ?? [] as $message) {
            $role = $message['role'] === 'system' ? 'Instructions given to the model (system)' : 'Message given to the model (user)';
            $parts[] = $role.":\n".mb_substr((string) $message['content'], 0, self::MAX_MESSAGE_CHARS);
        }
        $parts[] = "Model answer:\n".$result->content;

        return implode("\n\n", $parts);
    }
}

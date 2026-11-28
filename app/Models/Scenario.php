<?php

namespace App\Models;

use App\Enums\ResponseFormat;
use App\Scenarios\ScenarioDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $system_prompt
 * @property string $user_template
 * @property ResponseFormat $response_format
 * @property array<string, mixed>|null $json_schema
 * @property array<string, mixed>|null $params_json
 * @property array<string, array<string, mixed>>|null $metrics_json
 * @property string|null $judge_rubric
 * @property string $source
 * @property bool $available
 */
class Scenario extends Model
{
    protected $fillable = [
        'slug', 'name', 'system_prompt', 'user_template', 'response_format', 'json_schema',
        'params_json', 'metrics_json', 'judge_rubric', 'source', 'available',
    ];

    protected function casts(): array
    {
        return [
            'response_format' => ResponseFormat::class,
            'json_schema' => 'array',
            'params_json' => 'array',
            'metrics_json' => 'array',
            'available' => 'boolean',
        ];
    }

    public function toDefinition(): ScenarioDefinition
    {
        return new ScenarioDefinition(
            slug: $this->slug,
            name: $this->name,
            systemPrompt: $this->system_prompt,
            userTemplate: $this->user_template,
            responseFormat: $this->response_format,
            jsonSchema: $this->json_schema,
            params: $this->params_json ?? [],
            metrics: $this->metrics_json ?? [],
            judgeRubric: $this->judge_rubric,
        );
    }
}

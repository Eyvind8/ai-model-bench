<?php

namespace App\Models;

use App\Enums\JsonMode;
use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One model answer: run x model x dataset item x attempt.
 *
 * @property int $id
 * @property int $run_id
 * @property int $model_id
 * @property int|null $dataset_item_id
 * @property int $attempt_no
 * @property ResultStatus $status
 * @property array<string, mixed> $request_json
 * @property array<string, mixed>|null $raw_response
 * @property string|null $content
 * @property array<string, mixed>|null $parsed_json
 * @property JsonMode|null $json_mode
 * @property bool|null $json_valid
 * @property bool|null $schema_valid
 * @property int|null $latency_ms
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $http_status
 * @property string|null $error_message
 * @property int $retries
 * @property-read Run $run
 * @property-read LlmModel $model
 * @property-read DatasetItem|null $datasetItem
 */
class Result extends Model
{
    protected $fillable = [
        'run_id', 'model_id', 'dataset_item_id', 'attempt_no', 'status', 'request_json', 'raw_response',
        'content', 'parsed_json', 'json_mode', 'json_valid', 'schema_valid', 'latency_ms',
        'prompt_tokens', 'completion_tokens', 'http_status', 'error_message', 'retries',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResultStatus::class,
            'json_mode' => JsonMode::class,
            'request_json' => 'array',
            'raw_response' => 'array',
            'parsed_json' => 'array',
            'json_valid' => 'boolean',
            'schema_valid' => 'boolean',
            'attempt_no' => 'integer',
            'latency_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'http_status' => 'integer',
            'retries' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Run, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * @return BelongsTo<LlmModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'model_id');
    }

    /**
     * @return BelongsTo<DatasetItem, $this>
     */
    public function datasetItem(): BelongsTo
    {
        return $this->belongsTo(DatasetItem::class);
    }

    /**
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}

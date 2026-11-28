<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Scenarios\ScenarioDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $scenario_id
 * @property int|null $dataset_id
 * @property string|null $adhoc_input
 * @property int $repeats
 * @property RunStatus $status
 * @property array<string, mixed> $scenario_snapshot
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 * @property-read Scenario $scenario
 * @property-read Dataset|null $dataset
 */
class Run extends Model
{
    protected $fillable = [
        'scenario_id', 'dataset_id', 'adhoc_input', 'repeats', 'status',
        'scenario_snapshot', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'scenario_snapshot' => 'array',
            'repeats' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Scenario as it was when the run was started.
     */
    public function definition(): ScenarioDefinition
    {
        return ScenarioDefinition::fromArray((string) ($this->scenario_snapshot['slug'] ?? ''), $this->scenario_snapshot);
    }

    /**
     * @return BelongsTo<Scenario, $this>
     */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * @return BelongsToMany<LlmModel, $this>
     */
    public function models(): BelongsToMany
    {
        return $this->belongsToMany(LlmModel::class, 'run_models', 'run_id', 'model_id');
    }

    /**
     * @return HasMany<Result, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }
}

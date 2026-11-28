<?php

namespace App\Models;

use App\Enums\EvaluationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $result_id
 * @property EvaluationType $type
 * @property string $metric
 * @property float|null $value
 * @property string|null $details
 * @property int|null $judge_model_id
 */
class Evaluation extends Model
{
    protected $fillable = ['result_id', 'type', 'metric', 'value', 'details', 'judge_model_id'];

    protected function casts(): array
    {
        return [
            'type' => EvaluationType::class,
            'value' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }
}

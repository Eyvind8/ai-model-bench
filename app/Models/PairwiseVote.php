<?php

namespace App\Models;

use App\Enums\VoteWinner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Blind A/B vote between two answers to the same input.
 *
 * @property int $id
 * @property int $run_id
 * @property int $result_a_id
 * @property int $result_b_id
 * @property VoteWinner $winner
 * @property-read Result $resultA
 * @property-read Result $resultB
 */
class PairwiseVote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['run_id', 'result_a_id', 'result_b_id', 'winner'];

    protected function casts(): array
    {
        return ['winner' => VoteWinner::class];
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function resultA(): BelongsTo
    {
        return $this->belongsTo(Result::class, 'result_a_id');
    }

    /**
     * @return BelongsTo<Result, $this>
     */
    public function resultB(): BelongsTo
    {
        return $this->belongsTo(Result::class, 'result_b_id');
    }
}

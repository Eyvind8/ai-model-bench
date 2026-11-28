<?php

namespace App\Models;

use App\Enums\FetchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dataset_id
 * @property string|null $title
 * @property string $input
 * @property string|null $source_url
 * @property string|null $source_html
 * @property int|null $expected_score
 * @property string|null $expected_announcement
 * @property array<string, mixed>|null $expected_json
 * @property array<string, mixed>|null $meta_json
 * @property FetchStatus|null $fetch_status
 * @property string|null $fetch_error
 * @property-read Dataset $dataset
 */
class DatasetItem extends Model
{
    protected $fillable = [
        'dataset_id', 'title', 'input', 'source_url', 'source_html', 'expected_score',
        'expected_announcement', 'expected_json', 'meta_json', 'fetch_status', 'fetch_error',
    ];

    protected $attributes = ['input' => ''];

    protected function casts(): array
    {
        return [
            'expected_score' => 'integer',
            'expected_json' => 'array',
            'meta_json' => 'array',
            'fetch_status' => FetchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Dataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Items with text that can be sent to models.
     *
     * @param  Builder<self>  $query
     */
    public function scopeReady(Builder $query): void
    {
        $query->where('input', '!=', '')
            ->where(fn (Builder $q) => $q->whereNull('fetch_status')->orWhereIn('fetch_status', [FetchStatus::Ok, FetchStatus::Manual]));
    }
}

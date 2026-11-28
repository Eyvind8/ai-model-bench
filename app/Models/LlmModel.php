<?php

namespace App\Models;

use App\Enums\ModelSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A model offered by a provider (table `models`).
 *
 * @property int $id
 * @property int $provider_id
 * @property string $model_id
 * @property string|null $display_name
 * @property int|null $context_window
 * @property bool|null $supports_json
 * @property bool $is_free
 * @property ModelSource $source
 * @property bool $available
 * @property bool $enabled
 * @property bool $enabled_by_user
 * @property array<string, mixed>|null $limits_json
 * @property CarbonImmutable|null $synced_at
 * @property-read Provider $provider
 */
class LlmModel extends Model
{
    protected $table = 'models';

    protected $fillable = [
        'provider_id', 'model_id', 'display_name', 'context_window', 'supports_json',
        'is_free', 'source', 'available', 'enabled', 'enabled_by_user', 'limits_json', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'supports_json' => 'boolean',
            'is_free' => 'boolean',
            'source' => ModelSource::class,
            'available' => 'boolean',
            'enabled' => 'boolean',
            'enabled_by_user' => 'boolean',
            'limits_json' => 'array',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('enabled', true)->where('available', true);
    }

    public function ref(): string
    {
        return $this->provider->code.':'.$this->model_id;
    }
}

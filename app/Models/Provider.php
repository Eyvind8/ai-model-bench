<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $enabled
 * @property array<string, mixed>|null $limits_json
 */
class Provider extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'enabled', 'limits_json'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limits_json' => 'array',
        ];
    }

    /**
     * @return HasMany<LlmModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(LlmModel::class);
    }
}

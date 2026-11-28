<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Dataset extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * @return HasMany<DatasetItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DatasetItem::class);
    }
}

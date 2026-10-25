<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $min_selections
 * @property int $max_selections
 * @property bool $is_active
 * @property-read Collection<int, CatalogOption> $options
 */
class CatalogOptionGroup extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['min_selections' => 'integer', 'max_selections' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<CatalogOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CatalogOption::class, 'group_id');
    }
}

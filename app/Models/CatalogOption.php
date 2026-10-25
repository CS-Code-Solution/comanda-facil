<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property int|null $price_cents
 * @property int $max_quantity
 * @property bool $is_reviewed
 * @property bool $is_available
 * @property-read CatalogOptionGroup $group
 */
class CatalogOption extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'max_quantity' => 'integer', 'is_reviewed' => 'boolean', 'is_available' => 'boolean'];
    }

    /** @return BelongsTo<CatalogOptionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CatalogOptionGroup::class, 'group_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $code
 * @property string $unit
 * @property int|null $measure_value
 * @property string|null $pizza_size
 * @property int $price_cents
 * @property bool $is_reviewed
 * @property bool $is_active
 * @property bool $is_available
 * @property-read CatalogProduct $product
 */
class CatalogVariant extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['measure_value' => 'integer', 'price_cents' => 'integer', 'is_reviewed' => 'boolean', 'is_active' => 'boolean', 'is_available' => 'boolean'];
    }

    /** @return BelongsTo<CatalogProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'product_id');
    }
}

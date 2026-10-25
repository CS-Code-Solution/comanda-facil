<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $scope
 * @property int|null $category_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $discount_type
 * @property int $value
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $is_active
 */
class CatalogPromotion extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'integer', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}

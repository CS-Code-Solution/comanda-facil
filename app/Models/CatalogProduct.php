<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property string|null $source_ref
 * @property string|null $photo_path
 * @property list<array{name: string, quantity: int}> $composition
 * @property bool $is_reviewed
 * @property bool $is_active
 * @property bool $is_available
 * @property-read CatalogCategory $category
 * @property-read Collection<int, CatalogVariant> $variants
 * @property-read Collection<int, CatalogOptionGroup> $optionGroups
 */
class CatalogProduct extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['composition' => 'array', 'is_reviewed' => 'boolean', 'is_active' => 'boolean', 'is_available' => 'boolean'];
    }

    /** @return BelongsTo<CatalogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    /** @return HasMany<CatalogVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(CatalogVariant::class, 'product_id')->chaperone();
    }

    /** @return BelongsToMany<CatalogOptionGroup, $this> */
    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(CatalogOptionGroup::class, 'catalog_product_option_group', 'product_id', 'group_id');
    }
}

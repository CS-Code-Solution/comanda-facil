<?php

namespace App\Domain\Catalog;

use App\Models\CatalogProduct;
use App\Models\CatalogVariant;

class CatalogAvailability
{
    public static function product(CatalogProduct $product): bool
    {
        if (! $product->is_active || ! $product->is_reviewed || ! $product->is_available || ! $product->category->is_active
            || ($product->type === 'combo' && $product->composition === [])) {
            return false;
        }

        foreach ($product->optionGroups as $group) {
            if (! $group->is_active) {
                continue;
            }
            $capacity = $group->options->filter(fn ($option): bool => $option->is_available && $option->is_reviewed && $option->price_cents !== null)->sum('max_quantity');
            if ($capacity < $group->min_selections) {
                return false;
            }
        }

        return true;
    }

    public static function variant(CatalogVariant $variant): bool
    {
        return $variant->is_active && $variant->is_reviewed && $variant->is_available && self::product($variant->product);
    }
}

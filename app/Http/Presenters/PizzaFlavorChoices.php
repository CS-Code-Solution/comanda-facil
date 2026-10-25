<?php

namespace App\Http\Presenters;

use App\Models\CatalogVariant;
use Illuminate\Support\Collection;

class PizzaFlavorChoices
{
    /**
     * @param  Collection<int, CatalogVariant>  $available
     * @return list<array{id: int, productId: int, name: string}>
     */
    public static function forVariant(CatalogVariant $first, Collection $available): array
    {
        if ($first->product->type !== 'pizza' || $first->pizza_size === null) {
            return [];
        }
        $groups = $first->product->optionGroups->where('is_active', true)->pluck('id')->sort()->values()->all();

        return array_values($available->filter(fn (CatalogVariant $second): bool => $second->product_id !== $first->product_id
            && $second->pizza_size === $first->pizza_size
            && $second->product->optionGroups->where('is_active', true)->pluck('id')->sort()->values()->all() === $groups)
            ->sortBy(fn (CatalogVariant $variant): string => $variant->product->name)->values()
            ->map(fn (CatalogVariant $variant): array => ['id' => $variant->id, 'productId' => $variant->product_id, 'name' => $variant->product->name])->all());
    }
}

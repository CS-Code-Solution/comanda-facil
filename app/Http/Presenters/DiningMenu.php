<?php

namespace App\Http\Presenters;

use App\Domain\Catalog\CatalogAvailability;
use App\Domain\Catalog\Money;
use App\Enums\OrderStatus;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\DiningOrder;

class DiningMenu
{
    /** @return list<array<string, mixed>> */
    public static function products(?string $token = null, bool $pickup = false): array
    {
        $products = CatalogProduct::query()->with(['category', 'variants', 'optionGroups.options'])
            ->where('is_active', true)->where('is_reviewed', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('category_id')->orderBy('name')->get();
        $seconds = CatalogVariant::query()->with(['product.category', 'product.optionGroups.options'])->whereHas('product', fn ($query) => $query->where('type', 'pizza'))->get()
            ->filter(fn ($variant): bool => CatalogAvailability::variant($variant));

        return array_values($products->map(fn (CatalogProduct $product): array => [
            'id' => $product->id, 'name' => $product->name, 'category' => $product->category->name, 'description' => $product->description, 'composition' => $product->composition,
            'photo' => $product->photo_path ? ($pickup ? route('pickup.public.photo', $product) : ($token ? route('qr.photo', ['token' => $token, 'product' => $product->id]) : route('catalog.photo', $product))) : null,
            'variants' => $product->variants->filter(fn ($variant): bool => CatalogAvailability::variant($variant))->values()->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => Money::label($variant->price_cents), 'secondFlavors' => PizzaFlavorChoices::forVariant($variant, $seconds)]),
            'groups' => $product->optionGroups->where('is_active', true)->values()->map(fn ($group): array => ['id' => $group->id, 'name' => $group->name,
                'hint' => 'Escolhas: '.$group->min_selections.' a '.$group->max_selections,
                'options' => $group->options->filter(fn ($option): bool => $option->is_available && $option->is_reviewed && $option->price_cents !== null)->values()->map(fn ($option): array => ['id' => $option->id, 'name' => $option->name, 'price' => Money::label($option->price_cents), 'max' => $option->max_quantity])]),
        ])->all());
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function items(array $items): array
    {
        return array_map(fn (array $item): array => ['name' => $item['name'], 'presentation' => $item['variant_name'], 'quantity' => $item['quantity'],
            'total' => $item['total_label'], 'discount' => $item['discount_label'], 'appliedDiscount' => $item['applied_discount']['name'] ?? null,
            'note' => $item['note'] ?? null, 'options' => array_map(fn (array $option): string => $option['quantity'].' × '.$option['name'], $item['options'])], $items);
    }

    /** @return array<string, mixed> */
    public static function order(DiningOrder $order): array
    {
        $order->loadMissing('items');

        return ['reference' => $order->public_id, 'number' => $order->id, 'modality' => $order->modality, 'locationLabel' => $order->locationLabel(), 'tableNumber' => $order->table_number, 'status' => OrderStatus::from($order->status)->label($order->modality), 'total' => Money::label($order->status === 'cancelled' ? 0 : $order->total_cents),
            'version' => $order->version,
            'notice' => $order->status === 'cancelled' ? 'Pedido cancelado. Os valores dos itens são históricos; o valor devido deste pedido é zero.' : null,
            'timeline' => collect(['Confirmado' => $order->created_at, 'Em preparo' => $order->started_at, OrderStatus::Ready->label($order->modality) => $order->ready_at, 'Servido' => $order->served_at, 'Retirado' => $order->collected_at, 'Cancelado' => $order->cancelled_at])
                ->filter()->map(fn ($date, $label) => ['label' => $label, 'at' => $date->toIso8601String()])->values()->all(),
            'createdAt' => $order->created_at->toIso8601String(), 'items' => self::items(array_values($order->items->map(fn ($item): array => $item->snapshot)->all()))];
    }
}

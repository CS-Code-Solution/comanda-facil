<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\CatalogAvailability;
use App\Domain\Catalog\Money;
use App\Enums\StaffPermission;
use App\Models\CatalogPromotion;
use App\Models\CatalogVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuoteCatalogItem
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, CatalogContext $context): array
    {
        return DB::transaction(function () use ($input, $context): array {
            CatalogLock::shared();
            $context->authorize(StaffPermission::ViewCatalog);
            $data = Validator::make($input, [
                'variant_id' => 'required|integer|min:1', 'second_variant_id' => 'nullable|integer|min:1', 'quantity' => 'required|integer|between:1,99',
                'options' => 'nullable|array|max:50', 'options.*' => 'integer|between:0,20',
                'manual_discount' => 'nullable|array:type,value', 'manual_discount.type' => 'required_with:manual_discount|in:fixed,percentage',
                'manual_discount.value' => 'required_with:manual_discount|string',
            ])->validate();
            $first = $this->variant((int) $data['variant_id'], 'variant_id');
            $variants = [$first];

            if (! empty($data['second_variant_id'])) {
                $second = $this->variant((int) $data['second_variant_id'], 'second_variant_id');
                if ($first->product->type !== 'pizza' || $second->product->type !== 'pizza'
                    || $first->product_id === $second->product_id || $first->pizza_size === null || $first->pizza_size !== $second->pizza_size) {
                    throw ValidationException::withMessages(['second_variant_id' => 'Escolha outro sabor de pizza no mesmo tamanho.']);
                }
                $firstGroups = $first->product->optionGroups->where('is_active', true)->pluck('id')->sort()->values()->all();
                $secondGroups = $second->product->optionGroups->where('is_active', true)->pluck('id')->sort()->values()->all();
                if ($firstGroups !== $secondGroups) {
                    throw ValidationException::withMessages(['second_variant_id' => 'Os sabores precisam ter grupos de opções compatíveis.']);
                }
                $variants[] = $second;
            }

            $groups = $first->product->optionGroups->where('is_active', true);
            $allowedOptions = $groups->flatMap(fn ($group) => $group->options)->keyBy('id');
            $groupCounts = [];
            $options = [];
            $optionsCents = 0;

            foreach ($data['options'] ?? [] as $id => $quantity) {
                if ((int) $quantity === 0) {
                    continue;
                }
                $option = ctype_digit((string) $id) ? $allowedOptions->get((int) $id) : null;
                if (! $option || ! $option->is_available || ! $option->is_reviewed || $option->price_cents === null || (int) $quantity > $option->max_quantity) {
                    throw ValidationException::withMessages(['options' => 'Opção indisponível, não permitida ou com quantidade excedida.']);
                }
                $count = (int) $quantity;
                $groupCounts[$option->group_id] = ($groupCounts[$option->group_id] ?? 0) + $count;
                $amount = $option->price_cents * $count;
                $optionsCents += $amount;
                $options[] = ['id' => $option->id, 'name' => $option->name, 'quantity' => $count, 'unit_cents' => $option->price_cents, 'total_cents' => $amount];
            }

            foreach ($groups as $group) {
                $count = $groupCounts[$group->id] ?? 0;
                if ($count < $group->min_selections || $count > $group->max_selections) {
                    throw ValidationException::withMessages(['options' => $group->name.': escolha de '.$group->min_selections.' a '.$group->max_selections.' unidade(s).']);
                }
            }

            $base = max(array_map(fn (CatalogVariant $variant): int => $variant->price_cents, $variants));
            $gross = $base + $optionsCents;
            $discount = 0;
            $applied = null;
            $now = now();
            $promotions = CatalogPromotion::query()->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->orderBy('id')->get();
            foreach ($promotions as $promotion) {
                $eligible = true;
                foreach ($variants as $variant) {
                    $eligible = $eligible && match ($promotion->scope) {
                        'all' => true, 'category' => $promotion->category_id === $variant->product->category_id,
                        'product' => $promotion->product_id === $variant->product_id, 'variant' => $promotion->variant_id === $variant->id,
                        default => false,
                    };
                }
                if (! $eligible) {
                    continue;
                }
                $candidate = Money::discount($gross, $promotion->discount_type, $promotion->value);
                if ($candidate > $discount) {
                    $discount = $candidate;
                    $applied = ['source' => 'promotion', 'id' => $promotion->id, 'name' => $promotion->name, 'type' => $promotion->discount_type, 'value' => $promotion->value];
                }
            }

            if (! empty($data['manual_discount'])) {
                $context->authorize(StaffPermission::ManualDiscount);
                $manual = $data['manual_discount'];
                $value = Money::minor($manual['value'], 'manual_discount.value');
                if ($manual['type'] === 'percentage' && $value > 10000) {
                    throw ValidationException::withMessages(['manual_discount.value' => 'O percentual máximo é 100%.']);
                }
                $candidate = Money::discount($gross, $manual['type'], $value);
                if ($candidate > $discount) {
                    $discount = $candidate;
                    $applied = ['source' => 'manual', 'id' => null, 'name' => 'Desconto manual', 'type' => $manual['type'], 'value' => $value];
                }
            }

            $quantity = (int) $data['quantity'];
            $net = $gross - $discount;

            return [
                'product_id' => $first->product_id, 'variant_id' => $first->id, 'revision' => CatalogLock::revision(), 'quoted_at' => $now->toIso8601String(),
                'name' => implode(' / ', array_map(fn (CatalogVariant $variant): string => $variant->product->name, $variants)),
                'variant_name' => $first->name, 'unit' => $first->unit, 'measure_value' => $first->measure_value, 'pizza_size' => $first->pizza_size, 'quantity' => $quantity,
                'flavors' => array_map(fn (CatalogVariant $variant): array => ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'name' => $variant->product->name, 'base_cents' => $variant->price_cents], $variants),
                'composition' => $first->product->composition, 'options' => $options, 'base_unit_cents' => $base, 'options_unit_cents' => $optionsCents,
                'gross_unit_cents' => $gross, 'discount_unit_cents' => $discount, 'net_unit_cents' => $net,
                'gross_cents' => $gross * $quantity, 'discount_cents' => $discount * $quantity, 'total_cents' => $net * $quantity,
                'gross_label' => Money::label($gross * $quantity), 'discount_label' => Money::label($discount * $quantity),
                'unit_label' => Money::label($net), 'total_label' => Money::label($net * $quantity), 'applied_discount' => $applied,
            ];
        });
    }

    private function variant(int $id, string $field): CatalogVariant
    {
        $variant = CatalogVariant::query()->with(['product.category', 'product.optionGroups.options'])->find($id);
        if (! $variant || ! CatalogAvailability::variant($variant)) {
            throw ValidationException::withMessages([$field => 'Produto ou variação indisponível, arquivado ou ainda não revisado.']);
        }

        return $variant;
    }
}

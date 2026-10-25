<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\Money;
use App\Enums\CatalogResource;
use App\Models\CatalogCategory;
use App\Models\CatalogOption;
use App\Models\CatalogOptionGroup;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyncCatalogReference
{
    /** Existing reference identifiers remain stable even when displayed names change. */
    private const LEGACY = [
        'Pizza calabresa' => 'pizzas-calabresa',
        'Pizza camarão com catupiry' => 'pizzas-camarao-c-catupiry',
        'Pizza frango com catupiry' => 'pizzas-frango-catupiry',
        'Porção na brasa — picanha' => 'porcoes-na-brasa-picanha',
        'Churrasco picanha com queijo' => 'churrascos-picanha-c-queijo',
        'Suco de laranja' => 'sucos-laranja',
        'Coca-Cola' => 'bebidas-diversas-coca-cola',
        'Combinado Simples — 10 unidades' => 'combinados-simples',
    ];

    public function __construct(private ManageCatalog $catalog) {}

    public static function referenceNameForLegacy(string $slug): ?string
    {
        $name = array_search($slug, self::LEGACY, true);

        return $name === false ? null : $name;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array<string, int>
     */
    public function execute(array $products, bool $publish, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($products, $publish, $dryRun): array {
            CatalogLock::exclusive();
            $counts = ['products' => count($products), 'variants' => 0, 'matched' => 0, 'created' => 0,
                'updated' => 0, 'unchanged' => 0, 'retired_variants' => 0];
            $context = CatalogContext::console();
            $seen = [];
            foreach ($products as $input) {
                $record = $this->existing($input);
                if ($record && isset($seen[$record->id])) {
                    throw new RuntimeException('Duas linhas tentam alterar o mesmo produto: '.$input['name']);
                }
                if ($record) {
                    $seen[$record->id] = true;
                    $counts['matched']++;
                }
                foreach ($input['variants'] as $variant) {
                    Money::minor($variant['price'], 'price');
                    if ($input['type'] === 'combo' && array_sum(array_column($input['composition'], 'quantity')) !== $variant['measure_value']) {
                        throw new RuntimeException('Composição diverge das unidades: '.$input['name']);
                    }
                    $counts['variants']++;
                }
                if ($dryRun) {
                    continue;
                }
                $category = CatalogCategory::query()->where('slug', Str::slug($input['category']))->first();
                if (! $category) {
                    $category = $this->catalog->save(CatalogResource::Categories, ['name' => $input['category'], 'is_active' => true], $context);
                }
                $groups = $record ? $record->optionGroups()->pluck('catalog_option_groups.id')->all() : [];
                if ($input['churrasco']) {
                    $groups[] = $this->churrascoGroup($context);
                }
                $groups = array_values(array_unique($groups));
                $data = ['category_id' => $category->getKey(), 'name' => $input['name'], 'type' => $input['type'],
                    'description' => $input['description'], 'source_ref' => $input['source'], 'composition' => $input['composition'],
                    'option_group_ids' => $groups, 'is_reviewed' => true, 'is_available' => $record ? $record->is_available : true,
                    'is_active' => $publish || ($record ? $record->is_active : false)];
                if (! $record || $this->changed($record, $data)) {
                    $counts[$record ? 'updated' : 'created']++;
                    $saved = $this->catalog->save(CatalogResource::Products, $data, $context, $record?->id);
                    $record = CatalogProduct::query()->whereKey($saved->getKey())->firstOrFail();
                } else {
                    $counts['unchanged']++;
                }
                $ids = [];
                foreach ($input['variants'] as $variant) {
                    $existing = $this->variant($record, $variant);
                    $data = [...$variant, 'product_id' => $record->id, 'is_reviewed' => true,
                        'is_available' => $existing ? $existing->is_available : true, 'is_active' => $publish || ($existing ? $existing->is_active : false)];
                    $saved = ! $existing || $this->changed($existing, $data)
                        ? $this->catalog->save(CatalogResource::Variants, $data, $context, $existing?->id) : $existing;
                    $ids[] = $saved->getKey();
                }
                // Retire absent presentations, retaining IDs, historical snapshots and references.
                foreach ($record->variants()->whereNotIn('id', $ids)->where('is_active', true)->get() as $stale) {
                    $this->catalog->save(CatalogResource::Variants, ['product_id' => $record->id, 'name' => $stale->name,
                        'price' => Money::decimal($stale->price_cents), 'unit' => $stale->unit, 'measure_value' => $stale->measure_value,
                        'pizza_size' => $stale->pizza_size, 'is_reviewed' => $stale->is_reviewed,
                        'is_available' => $stale->is_available, 'is_active' => false], $context, $stale->id);
                    $counts['retired_variants']++;
                }
            }

            return $counts;
        });
    }

    /** @param array<string, mixed> $input */
    private function existing(array $input): ?CatalogProduct
    {
        $matches = CatalogProduct::query()->whereIn('slug', array_unique([$input['key'], self::LEGACY[$input['name']] ?? $input['key']]))->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Há cadastros duplicados para '.$input['name'].'. Concilie antes de sincronizar.');
        }

        return $matches->first();
    }

    /** @param array<string, mixed> $input */
    private function variant(CatalogProduct $record, array $input): ?CatalogVariant
    {
        $code = $input['pizza_size'] ?? Str::slug($input['name']);
        $existing = $record->variants()->where('code', $code)->first();
        if ($existing || $input['measure_value'] === null) {
            return $existing;
        }
        $matches = $record->variants()->where('unit', $input['unit'])->where('measure_value', $input['measure_value'])->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Apresentações ambíguas em '.$record->name);
        }

        return $matches->first();
    }

    private function churrascoGroup(CatalogContext $context): int
    {
        $name = 'Acompanhamento do churrasco — referência';
        $group = CatalogOptionGroup::query()->where('name', $name)->first();
        // The existing sample may already have the shared included choices.
        $data = ['name' => $name, 'min_selections' => 1, 'max_selections' => 1, 'is_active' => true];
        if (! $group || $this->changed($group, $data)) {
            $group = $this->catalog->save(CatalogResource::Groups, $data, $context, $group?->getKey());
        }
        foreach (['Batata', 'Macaxeira'] as $name) {
            $option = CatalogOption::query()->where('group_id', $group->getKey())->where('name', $name)->first();
            $data = ['group_id' => $group->getKey(), 'name' => $name, 'price' => '0,00', 'max_quantity' => 1,
                'is_reviewed' => true, 'is_available' => $option ? $option->is_available : true];
            if (! $option || $this->changed($option, $data)) {
                $this->catalog->save(CatalogResource::Options, $data, $context, $option?->id);
            }
        }

        return $group->getKey();
    }

    /** @param array<string, mixed> $data */
    private function changed(Model $record, array $data): bool
    {
        foreach ($data as $field => $value) {
            if ($field === 'option_group_ids' && $record instanceof CatalogProduct) {
                $current = $record->optionGroups()->pluck('catalog_option_groups.id')->all();
                sort($current);
                sort($value);
            } elseif ($field === 'price') {
                $current = $record->getAttribute('price_cents');
                $value = Money::minor($value, 'price', allowZero: true);
            } else {
                $current = $record->getAttribute($field);
            }
            if ($current != $value) {
                return true;
            }
        }

        return false;
    }
}

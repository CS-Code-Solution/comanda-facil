<?php

namespace App\Application\Catalog;

use App\Domain\Catalog\Money;
use App\Enums\CatalogResource;
use App\Enums\StaffPermission;
use App\Models\CatalogCategory;
use App\Models\CatalogOption;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageCatalog
{
    /** @param array<string, mixed> $input */
    public function save(CatalogResource $resource, array $input, CatalogContext $context, ?int $id = null): Model
    {
        try {
            return DB::transaction(function () use ($resource, $input, $context, $id): Model {
                CatalogLock::exclusive();
                $context->authorize(StaffPermission::ManageCatalog);
                $class = $resource->model();
                $record = $id === null ? new $class : $class::query()->findOrFail($id);
                $before = $record->exists ? $this->snapshot($record) : null;
                $data = match ($resource) {
                    CatalogResource::Categories => $this->categoryData($input, $record),
                    CatalogResource::Products => $this->productData($input, $record instanceof CatalogProduct ? $record : null),
                    CatalogResource::Variants => $this->variantData($input, $record instanceof CatalogVariant ? $record : null),
                    CatalogResource::Groups => $this->groupData($input),
                    CatalogResource::Options => $this->optionData($input, $record instanceof CatalogOption ? $record : null),
                    CatalogResource::Promotions => $this->promotionData($input),
                };
                $groups = $data['option_group_ids'] ?? [];
                unset($data['option_group_ids']);
                $record->forceFill($data)->save();

                if ($record instanceof CatalogProduct) {
                    $record->optionGroups()->sync($groups);
                }

                $record->refresh();
                $this->record($resource, $record, $context, $before === null ? 'created' : 'updated', $before);

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Já existe um cadastro com esse identificador ou tamanho.']);
        }
    }

    public function setPhoto(CatalogProduct $product, ?string $path, CatalogContext $context): CatalogProduct
    {
        if ($path !== null && (! str_starts_with($path, 'catalog/photos/') || str_contains($path, '..'))) {
            throw ValidationException::withMessages(['photo' => 'Caminho de imagem inválido.']);
        }

        return DB::transaction(function () use ($product, $path, $context): CatalogProduct {
            CatalogLock::exclusive();
            $context->authorize(StaffPermission::ManageCatalog);
            $account = CatalogProduct::query()->findOrFail($product->id);
            $old = $account->photo_path;
            $before = $this->snapshot($account);
            $account->forceFill(['photo_path' => $path])->save();
            $this->record(CatalogResource::Products, $account, $context, 'photo_changed', $before);

            if ($old && $old !== $path) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($old));
            }

            return $account;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function categoryData(array $input, Model $record): array
    {
        $data = Validator::make($input, ['name' => 'required|string|max:120', 'sort_order' => 'nullable|integer|between:0,9999', 'is_active' => 'boolean'])->validate();

        return ['name' => $data['name'], 'slug' => $record->exists ? $record->getAttribute('slug') : $this->slug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0, 'is_active' => (bool) ($data['is_active'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function productData(array $input, ?CatalogProduct $record): array
    {
        $data = Validator::make($input, [
            'category_id' => 'required|integer|exists:catalog_categories,id', 'name' => 'required|string|max:160',
            'type' => 'required|in:simple,pizza,combo', 'description' => 'nullable|string|max:6000', 'source_ref' => 'nullable|string|max:255',
            'is_active' => 'boolean', 'is_available' => 'boolean', 'is_reviewed' => 'boolean',
            'composition' => 'nullable|array|max:50', 'composition.*' => 'array:name,quantity',
            'composition.*.name' => 'required|string|max:160', 'composition.*.quantity' => 'required|integer|between:1,999',
            'option_group_ids' => 'nullable|array|max:20', 'option_group_ids.*' => 'integer|distinct|exists:catalog_option_groups,id',
        ])->validate();
        $this->review($data);

        if ($record?->exists && $record->type !== $data['type'] && $record->variants()->exists()) {
            throw ValidationException::withMessages(['type' => 'O tipo não pode ser alterado depois de cadastrar variações.']);
        }

        $composition = array_map(fn (array $part): array => ['name' => $part['name'], 'quantity' => (int) $part['quantity']], $data['composition'] ?? []);

        if ($data['type'] === 'combo' && $composition === []) {
            throw ValidationException::withMessages(['composition' => 'Informe a composição fixa do combo.']);
        }
        if ($data['type'] !== 'combo' && $composition !== []) {
            throw ValidationException::withMessages(['composition' => 'Composição estruturada é usada em combos.']);
        }
        if ($record?->exists && $data['type'] === 'combo') {
            foreach ($record->variants()->where('unit', 'pieces')->get() as $variant) {
                if (array_sum(array_column($composition, 'quantity')) !== $variant->measure_value) {
                    throw ValidationException::withMessages(['composition' => 'A composição deve corresponder às unidades das variações.']);
                }
            }
        }

        $category = CatalogCategory::query()->findOrFail((int) $data['category_id']);

        return [
            'category_id' => $category->id, 'name' => $data['name'],
            'slug' => $record?->exists ? $record->slug : Str::limit($this->slug($category->slug.' '.$data['name']), 240, ''),
            'type' => $data['type'], 'description' => $data['description'] ?? null, 'source_ref' => $data['source_ref'] ?? null,
            'composition' => $composition, 'is_active' => (bool) ($data['is_active'] ?? false),
            'is_available' => (bool) ($data['is_available'] ?? false), 'is_reviewed' => (bool) ($data['is_reviewed'] ?? false),
            'option_group_ids' => $data['option_group_ids'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function variantData(array $input, ?CatalogVariant $record): array
    {
        $data = Validator::make($input, [
            'product_id' => 'required|integer|exists:catalog_products,id', 'name' => 'required|string|max:120', 'price' => 'required|string',
            'unit' => 'required|in:presentation,g,ml,people,pieces,size', 'measure_value' => 'nullable|integer|between:1,100000',
            'pizza_size' => 'nullable|in:p,g,f', 'is_active' => 'boolean', 'is_available' => 'boolean', 'is_reviewed' => 'boolean',
        ])->validate();
        $this->review($data);
        $product = CatalogProduct::query()->findOrFail((int) $data['product_id']);

        if ($record?->exists && $record->product_id !== $product->id) {
            throw ValidationException::withMessages(['product_id' => 'Uma variação existente não pode mudar de produto.']);
        }
        if ($product->type === 'pizza' && (empty($data['pizza_size']) || $data['unit'] !== 'size')) {
            throw ValidationException::withMessages(['pizza_size' => 'Pizza exige tamanho P/G/F e unidade Tamanho.']);
        }
        if ($product->type !== 'pizza' && ! empty($data['pizza_size'])) {
            throw ValidationException::withMessages(['pizza_size' => 'Tamanho de pizza não se aplica a este produto.']);
        }
        $numeric = in_array($data['unit'], ['g', 'ml', 'people', 'pieces'], true);
        if ($numeric && empty($data['measure_value'])) {
            throw ValidationException::withMessages(['measure_value' => 'Informe a quantidade da apresentação.']);
        }
        if ($product->type === 'combo' && $data['unit'] === 'pieces' && array_sum(array_column($product->composition, 'quantity')) !== (int) ($data['measure_value'] ?? 0)) {
            throw ValidationException::withMessages(['measure_value' => 'As unidades devem corresponder à composição fixa.']);
        }

        return [
            'product_id' => $product->id, 'name' => $data['name'], 'code' => $product->type === 'pizza' ? $data['pizza_size'] : $this->slug($data['name']),
            'unit' => $data['unit'], 'measure_value' => $numeric ? (int) $data['measure_value'] : null,
            'pizza_size' => $data['pizza_size'] ?? null, 'price_cents' => Money::minor($data['price'], 'price'),
            'is_active' => (bool) ($data['is_active'] ?? false), 'is_available' => (bool) ($data['is_available'] ?? false), 'is_reviewed' => (bool) ($data['is_reviewed'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function groupData(array $input): array
    {
        $data = Validator::make($input, ['name' => 'required|string|max:120', 'min_selections' => 'required|integer|between:0,20',
            'max_selections' => 'required|integer|between:1,20|gte:min_selections', 'is_active' => 'boolean'])->validate();

        return ['name' => $data['name'], 'min_selections' => (int) $data['min_selections'], 'max_selections' => (int) $data['max_selections'], 'is_active' => (bool) ($data['is_active'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function optionData(array $input, ?CatalogOption $record): array
    {
        $data = Validator::make($input, ['group_id' => 'required|integer|exists:catalog_option_groups,id', 'name' => 'required|string|max:120',
            'price' => 'nullable|string', 'max_quantity' => 'required|integer|between:1,20', 'is_reviewed' => 'boolean', 'is_available' => 'boolean'])->validate();
        if ($record?->exists && $record->group_id !== (int) $data['group_id']) {
            throw ValidationException::withMessages(['group_id' => 'Uma opção existente não pode mudar de grupo.']);
        }
        $price = isset($data['price']) && $data['price'] !== '' ? Money::minor($data['price'], 'price', true) : null;

        if (! empty($data['is_available']) && (empty($data['is_reviewed']) || $price === null)) {
            throw ValidationException::withMessages(['price' => 'Confira o preço antes de disponibilizar a opção. Zero deve ser explícito para itens incluídos.']);
        }

        return ['group_id' => (int) $data['group_id'], 'name' => $data['name'], 'price_cents' => $price,
            'max_quantity' => (int) $data['max_quantity'], 'is_reviewed' => (bool) ($data['is_reviewed'] ?? false), 'is_available' => (bool) ($data['is_available'] ?? false)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function promotionData(array $input): array
    {
        $data = Validator::make($input, ['name' => 'required|string|max:160', 'scope' => 'required|in:all,category,product,variant',
            'category_id' => 'nullable|integer|exists:catalog_categories,id', 'product_id' => 'nullable|integer|exists:catalog_products,id', 'variant_id' => 'nullable|integer|exists:catalog_variants,id',
            'discount_type' => 'required|in:fixed,percentage', 'discount_value' => 'required|string', 'is_active' => 'boolean',
            'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i'])->validate();
        $value = Money::minor($data['discount_value'], 'discount_value');
        if ($data['discount_type'] === 'percentage' && $value > 10000) {
            throw ValidationException::withMessages(['discount_value' => 'O percentual máximo é 100%.']);
        }
        $start = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], config('catalog.timezone'))->startOfMinute()->utc();
        $end = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at'], config('catalog.timezone'))->startOfMinute()->utc();
        if ($end <= $start) {
            throw ValidationException::withMessages(['ends_at' => 'O fim deve ser posterior ao início.']);
        }
        $targets = [];
        foreach (['category', 'product', 'variant'] as $scope) {
            $field = $scope.'_id';
            if ($data['scope'] === $scope && empty($data[$field])) {
                throw ValidationException::withMessages([$field => 'Selecione o alvo da promoção.']);
            }
            if ($data['scope'] !== $scope && ! empty($data[$field])) {
                throw ValidationException::withMessages([$field => 'O alvo não corresponde à abrangência selecionada.']);
            }
            $targets[$field] = $data['scope'] === $scope ? (int) $data[$field] : null;
        }

        return [...$targets, 'name' => $data['name'], 'scope' => $data['scope'], 'discount_type' => $data['discount_type'], 'value' => $value,
            'starts_at' => $start, 'ends_at' => $end, 'is_active' => (bool) ($data['is_active'] ?? false)];
    }

    /** @param array<string, mixed> $data */
    private function review(array $data): void
    {
        if (! empty($data['is_active']) && empty($data['is_reviewed'])) {
            throw ValidationException::withMessages(['is_reviewed' => 'Confira os dados antes de publicar.']);
        }
    }

    private function slug(string $name): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => 'Informe um nome com letras ou números.']);
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $record): array
    {
        $data = $record->attributesToArray();
        unset($data['photo_path'], $data['created_at'], $data['updated_at']);
        if ($record instanceof CatalogProduct) {
            $data['option_group_ids'] = $record->optionGroups()->orderBy('catalog_option_groups.id')->pluck('catalog_option_groups.id')->all();
            $data['has_photo'] = $record->photo_path !== null;
        }

        return $data;
    }

    /** @param array<string, mixed>|null $before */
    private function record(CatalogResource $resource, Model $record, CatalogContext $context, string $action, ?array $before): void
    {
        DB::table('catalog_state')->where('id', 1)->increment('revision');
        DB::table('catalog_events')->insert(['actor_id' => $context->actorId, 'source' => $context->source, 'entity' => $resource->value,
            'entity_id' => $record->getKey(), 'action' => $action, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($this->snapshot($record), JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
    }
}

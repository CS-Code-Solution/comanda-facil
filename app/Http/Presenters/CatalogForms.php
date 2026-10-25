<?php

namespace App\Http\Presenters;

use App\Domain\Catalog\Money;
use App\Enums\CatalogResource;
use App\Models\CatalogCategory;
use App\Models\CatalogOptionGroup;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Model;

class CatalogForms
{
    /**
     * @param  array<string, mixed>  $prefill
     * @return list<array<string, mixed>>
     */
    public function fields(CatalogResource $resource, ?Model $record, array $prefill = []): array
    {
        $value = fn (string $name, mixed $default = '') => $record?->getAttribute($name) ?? $prefill[$name] ?? $default;
        $field = fn (string $name, string $label, string $type = 'text', mixed $default = '', array $extra = []): array => ['name' => $name, 'label' => $label, 'type' => $type, 'value' => $value($name, $default), ...$extra];
        $review = [
            $field('is_reviewed', 'Dados e preço conferidos', 'checkbox', false),
            $field('is_active', 'Publicado', 'checkbox', false),
            $field('is_available', 'Disponível para venda', 'checkbox', true),
        ];
        $categories = fn (): array => CatalogCategory::query()->orderBy('name')->get()->map(fn ($item): array => ['value' => $item->id, 'label' => $item->name])->all();
        $products = fn (): array => CatalogProduct::query()->with('category')->orderBy('name')->get()->map(fn ($item): array => ['value' => $item->id, 'label' => $item->category->name.' / '.$item->name])->all();
        $groups = fn (): array => CatalogOptionGroup::query()->orderBy('name')->get()->map(fn ($item): array => ['value' => $item->id, 'label' => $item->name])->all();

        return match ($resource) {
            CatalogResource::Categories => [$field('name', 'Nome'), $field('sort_order', 'Ordem de exibição', 'number', 0), $field('is_active', 'Categoria ativa', 'checkbox', true)],
            CatalogResource::Products => [
                $field('category_id', 'Categoria', 'select', '', ['options' => $categories()]), $field('name', 'Nome'),
                $field('type', 'Tipo', 'select', 'simple', ['options' => $this->choices(['simple' => 'Produto simples', 'pizza' => 'Sabor de pizza', 'combo' => 'Combo fixo']),
                    'disabled' => $record instanceof CatalogProduct && $record->variants()->exists()]),
                $field('description', 'Descrição e acompanhamentos', 'textarea'),
                $field('composition', 'Composição fixa do combo', 'composition', [], ['depends_on' => 'type', 'when' => 'combo']),
                ['name' => 'option_group_ids', 'label' => 'Grupos de opções e adicionais', 'type' => 'multiselect', 'value' => $record instanceof CatalogProduct ? $record->optionGroups()->pluck('catalog_option_groups.id')->all() : [], 'options' => $groups()],
                $field('source_ref', 'Origem ou referência da conferência'), ...$review,
            ],
            CatalogResource::Variants => [
                $field('product_id', 'Produto', 'select', '', ['options' => $products(), 'disabled' => $record !== null]),
                $field('name', 'Apresentação (ex.: 500 g, copo, pequena)'),
                $field('unit', 'Unidade da apresentação', 'select', 'presentation', ['options' => $this->choices(['presentation' => 'Apresentação', 'g' => 'Gramas', 'ml' => 'Mililitros', 'people' => 'Pessoas', 'pieces' => 'Unidades/peças', 'size' => 'Tamanho de pizza'])]),
                $field('measure_value', 'Quantidade da apresentação', 'number', '', ['hint' => 'Para gramas, ml, pessoas ou peças; não é pesagem variável.']),
                $field('pizza_size', 'Tamanho (somente pizzas)', 'select', '', ['options' => $this->choices(['p' => 'Pequena', 'g' => 'Grande', 'f' => 'Família'])]),
                ['name' => 'price', 'label' => 'Preço fixo em R$', 'type' => 'text', 'value' => $record ? Money::decimal((int) $record->getAttribute('price_cents')) : '', 'hint' => 'Exemplo: 32,00. Sem separador de milhar.'], ...$review,
            ],
            CatalogResource::Groups => [
                $field('name', 'Nome do grupo'), $field('min_selections', 'Mínimo de escolhas', 'number', 0),
                $field('max_selections', 'Máximo de escolhas', 'number', 1), $field('is_active', 'Grupo ativo', 'checkbox', false),
            ],
            CatalogResource::Options => [
                $field('group_id', 'Grupo', 'select', '', ['options' => $groups(), 'disabled' => $record !== null]), $field('name', 'Nome da opção'),
                ['name' => 'price', 'label' => 'Acréscimo em R$', 'type' => 'text', 'value' => $record?->getAttribute('price_cents') !== null ? Money::decimal((int) $record->getAttribute('price_cents')) : '', 'hint' => 'Vazio = pendente. Use 0,00 somente para opção incluída já conferida.'],
                $field('max_quantity', 'Quantidade máxima desta opção', 'number', 1), $field('is_reviewed', 'Preço conferido', 'checkbox', false),
                $field('is_available', 'Opção disponível', 'checkbox', false),
            ],
            CatalogResource::Promotions => [
                $field('name', 'Nome da promoção'), $field('scope', 'Abrangência', 'select', 'all', ['options' => $this->choices(['all' => 'Todo o cardápio', 'category' => 'Categoria', 'product' => 'Produto', 'variant' => 'Variação'])]),
                $field('category_id', 'Categoria da promoção', 'select', '', ['options' => $categories(), 'depends_on' => 'scope', 'when' => 'category']),
                $field('product_id', 'Produto da promoção', 'select', '', ['options' => $products(), 'depends_on' => 'scope', 'when' => 'product']),
                $field('variant_id', 'Variação da promoção', 'select', '', ['options' => CatalogVariant::query()->with('product')->orderBy('id')->get()->map(fn ($item): array => ['value' => $item->id, 'label' => $item->product->name.' / '.$item->name])->all(), 'depends_on' => 'scope', 'when' => 'variant']),
                $field('discount_type', 'Tipo de desconto', 'select', 'percentage', ['options' => $this->choices(['percentage' => 'Percentual (%)', 'fixed' => 'Valor fixo (R$)'])]),
                ['name' => 'discount_value', 'label' => 'Valor do desconto', 'type' => 'text', 'value' => $record ? Money::decimal((int) $record->getAttribute('value')) : '', 'hint' => 'Ex.: 10,00 para 10% ou R$ 10,00, conforme o tipo. Não acumula com outros descontos.'],
                ['name' => 'starts_at', 'label' => 'Início', 'type' => 'datetime-local', 'value' => $record ? $record->getAttribute('starts_at')->setTimezone(config('catalog.timezone'))->format('Y-m-d\TH:i') : '', 'hint' => 'Horário do restaurante: '.config('catalog.timezone')],
                ['name' => 'ends_at', 'label' => 'Fim (não inclusivo)', 'type' => 'datetime-local', 'value' => $record ? $record->getAttribute('ends_at')->setTimezone(config('catalog.timezone'))->format('Y-m-d\TH:i') : ''],
                $field('is_active', 'Promoção ativa', 'checkbox', false),
            ],
        };
    }

    /**
     * @param  array<string, string>  $values
     * @return list<array{value: string, label: string}>
     */
    private function choices(array $values): array
    {
        $result = [];
        foreach ($values as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }
}

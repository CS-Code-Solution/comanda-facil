<?php

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\QuoteCatalogItem;
use App\Domain\Catalog\CatalogAvailability;
use App\Domain\Catalog\Money;
use App\Enums\StaffPermission;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['q' => 'nullable|string|max:100', 'category_id' => 'nullable|integer']);
        $products = CatalogProduct::query()->with(['category', 'variants', 'optionGroups.options'])
            ->where('is_active', true)->where('is_reviewed', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('q')->toString().'%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->orderBy('category_id')->orderBy('name')->paginate(24)->withQueryString();

        return Inertia::render('catalog/index', [
            'products' => $products->getCollection()->map(fn (CatalogProduct $product): array => [
                'id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'category' => $product->category->name,
                'available' => CatalogAvailability::product($product) && $product->variants->contains(fn ($variant): bool => CatalogAvailability::variant($variant)),
                'photo' => $product->photo_path ? route('catalog.photo', $product) : null, 'url' => route('catalog.show', $product),
            ]),
            'categories' => CatalogCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'category_id']),
            'pagination' => ['previous' => $products->previousPageUrl(), 'next' => $products->nextPageUrl()],
            'adminUrl' => Gate::allows(StaffPermission::ManageCatalog->value) ? route('catalog.admin.home') : null,
        ]);
    }

    public function show(CatalogProduct $product): Response
    {
        return $this->page($product, session('catalog_quote'));
    }

    public function quote(Request $request, CatalogProduct $product, QuoteCatalogItem $quote): RedirectResponse
    {
        $request->validate(['variant_id' => 'required|integer']);
        if (! $product->variants()->whereKey($request->integer('variant_id'))->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'A apresentação não pertence a este produto.']);
        }

        $result = $quote->execute($request->all(), CatalogContext::web($request->user()));

        return to_route('catalog.show', $product)->with('catalog_quote', $result);
    }

    /** @param array<string, mixed>|null $quote */
    private function page(CatalogProduct $product, ?array $quote = null): Response
    {
        $product->load(['category', 'variants', 'optionGroups.options']);
        abort_unless($product->is_active && $product->is_reviewed && $product->category->is_active, 404);
        if ($quote !== null && $quote['product_id'] !== $product->id) {
            $quote = null;
        }
        $variants = $product->variants->filter(fn ($variant): bool => CatalogAvailability::variant($variant))->values();
        $seconds = $product->type === 'pizza' ? CatalogVariant::query()->with(['product.category', 'product.optionGroups.options'])
            ->where('product_id', '!=', $product->id)->whereHas('product', fn ($query) => $query->where('type', 'pizza'))->get()
            ->filter(fn ($variant): bool => CatalogAvailability::variant($variant))->values() : collect();

        return Inertia::render('catalog/show', [
            'product' => ['name' => $product->name, 'description' => $product->description, 'composition' => $product->composition,
                'photo' => $product->photo_path ? route('catalog.photo', $product) : null, 'isPizza' => $product->type === 'pizza'],
            'variants' => $variants->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => Money::label($variant->price_cents)]),
            'secondVariants' => $seconds->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->product->name.' / '.$variant->name]),
            'groups' => $product->optionGroups->where('is_active', true)->values()->map(fn ($group): array => [
                'id' => $group->id, 'name' => $group->name, 'hint' => 'Escolhas: mínimo '.$group->min_selections.', máximo '.$group->max_selections,
                'options' => $group->options->filter(fn ($option): bool => $option->is_available && $option->is_reviewed && $option->price_cents !== null)->values()->map(fn ($option): array => [
                    'id' => $option->id, 'name' => $option->name, 'price' => Money::label($option->price_cents), 'max' => $option->max_quantity,
                ]),
            ]),
            'canManualDiscount' => Gate::allows(StaffPermission::ManualDiscount->value), 'quoteUrl' => route('catalog.quote', $product), 'quote' => $quote,
        ]);
    }
}

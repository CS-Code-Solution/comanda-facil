<?php

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\ManageCatalog;
use App\Domain\Catalog\Money;
use App\Enums\CatalogResource;
use App\Http\Presenters\CatalogForms;
use App\Models\CatalogEvent;
use App\Models\CatalogProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogAdminController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $type = $this->resource($resource);
        $request->validate(['q' => 'nullable|string|max:100', 'product_id' => 'nullable|integer', 'group_id' => 'nullable|integer']);
        $class = $type->model();
        $query = $class::query()->when($request->filled('q'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('q')->toString().'%'));
        if ($type === CatalogResource::Variants && $request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        if ($type === CatalogResource::Options && $request->filled('group_id')) {
            $query->where('group_id', $request->integer('group_id'));
        }
        $records = $query->orderBy('id')->paginate(20)->withQueryString();

        return Inertia::render('catalog/admin/index', [
            'title' => $type->label(), 'tabs' => $this->tabs($type), 'createUrl' => route('catalog.admin.create', ['resource' => $type->value, ...$request->only(['product_id', 'group_id'])]),
            'rows' => $records->getCollection()->map(fn (Model $record): array => $this->row($type, $record)),
            'pagination' => ['previous' => $records->previousPageUrl(), 'next' => $records->nextPageUrl(), 'current' => $records->currentPage(), 'last' => $records->lastPage(), 'total' => $records->total()],
            'searchUrl' => route('catalog.admin.index', $type->value), 'filters' => $request->only(['q', 'product_id', 'group_id']),
        ]);
    }

    public function create(Request $request, string $resource, CatalogForms $forms): Response
    {
        $type = $this->resource($resource);
        $request->validate(['product_id' => 'nullable|integer|exists:catalog_products,id', 'group_id' => 'nullable|integer|exists:catalog_option_groups,id']);

        return $this->form($type, null, $forms, $request->only(['product_id', 'group_id']));
    }

    public function edit(string $resource, int $id, CatalogForms $forms): Response
    {
        $type = $this->resource($resource);
        $class = $type->model();

        return $this->form($type, $class::query()->findOrFail($id), $forms);
    }

    public function store(Request $request, string $resource, ManageCatalog $catalog): RedirectResponse
    {
        $type = $this->resource($resource);
        $record = $catalog->save($type, $request->all(), CatalogContext::web($request->user()));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cadastro salvo.']);

        return to_route('catalog.admin.edit', ['resource' => $type->value, 'id' => $record->getKey()]);
    }

    public function update(Request $request, string $resource, int $id, ManageCatalog $catalog): RedirectResponse
    {
        $type = $this->resource($resource);
        $catalog->save($type, $request->all(), CatalogContext::web($request->user()), $id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alterações salvas.']);

        return to_route('catalog.admin.edit', ['resource' => $type->value, 'id' => $id]);
    }

    private function resource(string $resource): CatalogResource
    {
        return CatalogResource::tryFrom($resource) ?? abort(404);
    }

    /** @return list<array<string, mixed>> */
    private function tabs(CatalogResource $active): array
    {
        return array_map(fn (CatalogResource $resource): array => ['label' => $resource->label(), 'url' => route('catalog.admin.index', $resource->value), 'active' => $resource === $active], CatalogResource::cases());
    }

    /** @return array<string, mixed> */
    private function row(CatalogResource $resource, Model $record): array
    {
        $price = $record->getAttribute('price_cents');
        $status = $record->getAttribute('is_active') ?? $record->getAttribute('is_available');
        $reviewed = $record->getAttribute('is_reviewed');

        return ['id' => $record->getKey(), 'name' => $record->getAttribute('name'),
            'detail' => $price !== null ? Money::label((int) $price) : ($resource === CatalogResource::Options ? 'Preço pendente' : ''),
            'status' => $reviewed === false ? 'Pendente de revisão' : ($record->getAttribute('is_available') === false ? 'Indisponível' : ($status ? 'Ativo' : 'Inativo / rascunho')),
            'url' => route('catalog.admin.edit', ['resource' => $resource->value, 'id' => $record->getKey()])];
    }

    /** @param array<string, mixed> $prefill */
    private function form(CatalogResource $type, ?Model $record, CatalogForms $forms, array $prefill = []): Response
    {
        $id = $record?->getKey();
        $links = [];
        $photo = null;
        if ($record instanceof CatalogProduct) {
            $links = [
                ['label' => 'Variações e preços', 'url' => route('catalog.admin.index', ['resource' => CatalogResource::Variants->value, 'product_id' => $id])],
                ['label' => 'Nova variação', 'url' => route('catalog.admin.create', ['resource' => CatalogResource::Variants->value, 'product_id' => $id])],
            ];
            $photo = ['url' => $record->photo_path ? route('catalog.photo', $record) : null,
                'uploadUrl' => route('catalog.photo.store', $record), 'deleteUrl' => route('catalog.photo.destroy', $record)];
        }
        if ($type === CatalogResource::Groups && $id !== null) {
            $links = [['label' => 'Opções deste grupo', 'url' => route('catalog.admin.index', ['resource' => CatalogResource::Options->value, 'group_id' => $id])],
                ['label' => 'Nova opção', 'url' => route('catalog.admin.create', ['resource' => CatalogResource::Options->value, 'group_id' => $id])]];
        }

        return Inertia::render('catalog/admin/form', [
            'title' => $type->label(), 'editing' => $record !== null, 'fields' => $forms->fields($type, $record, $prefill),
            'submitUrl' => $record ? route('catalog.admin.update', ['resource' => $type->value, 'id' => $id]) : route('catalog.admin.store', $type->value),
            'backUrl' => route('catalog.admin.index', $type->value), 'links' => $links, 'photo' => $photo,
            'events' => $record ? CatalogEvent::query()->where('entity', $type->value)->where('entity_id', $id)->with('actor:id,name')->orderByDesc('id')->limit(10)->get()->map(fn (CatalogEvent $event): array => [
                'id' => $event->id, 'action' => match ($event->action) {
                    'created' => 'Cadastro criado', 'photo_changed' => 'Foto alterada', default => 'Cadastro atualizado'
                },
                'actor' => $event->actor->name ?? 'Operador do servidor', 'at' => $event->occurred_at->toIso8601String(),
                'priceBefore' => isset($event->before['price_cents']) ? Money::label((int) $event->before['price_cents']) : null,
                'priceAfter' => isset($event->after['price_cents']) ? Money::label((int) $event->after['price_cents']) : null,
            ]) : [],
        ]);
    }
}

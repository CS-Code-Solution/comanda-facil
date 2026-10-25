<?php

namespace App\Http\Controllers;

use App\Application\Delivery\DeliveryCoverage;
use App\Application\Dining\DiningContext;
use App\Domain\Catalog\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryCoverageController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['edit' => 'nullable|integer|min:1']);
        $areas = DB::table('delivery_areas')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $edit = $request->input('edit') ? DB::table('delivery_areas')->where('id', $request->integer('edit'))->firstOrFail() : null;

        return Inertia::render('delivery/areas', ['areas' => $areas->getCollection()->map(fn ($area) => ['id' => $area->id, 'name' => $area->name,
            'coverage' => $area->city.'/'.$area->state.' · '.($area->neighborhood ?? 'Todos os bairros').' · '.($area->cep_start ? $area->cep_start.'–'.$area->cep_end : 'Qualquer CEP'),
            'fee' => Money::label($area->fee_cents), 'minimum' => Money::label($area->minimum_cents), 'active' => $area->is_active,
            'url' => route('delivery.areas.index', ['edit' => $area->id])]),
            'pagination' => ['previous' => $areas->previousPageUrl(), 'next' => $areas->nextPageUrl()],
            'editing' => $edit ? ['id' => $edit->id, 'name' => $edit->name, 'city' => $edit->city, 'state' => $edit->state,
                'neighborhood' => $edit->neighborhood ?? '', 'cep_start' => $edit->cep_start ?? '', 'cep_end' => $edit->cep_end ?? '',
                'fee' => Money::decimal($edit->fee_cents), 'minimum' => Money::decimal($edit->minimum_cents), 'version' => $edit->version, 'is_active' => $edit->is_active] : null,
            'saveUrl' => $edit ? route('delivery.areas.update', $edit->id) : route('delivery.areas.store'),
            'serviceUrl' => route('delivery.service'), 'receiving' => (bool) DB::table('delivery_settings')->where('id', 1)->value('accepting_orders')]);
    }

    public function save(Request $request, DeliveryCoverage $coverage, ?int $area = null): RedirectResponse
    {
        $coverage->save($area, $request->all(), DiningContext::staff($request->user()));

        return to_route('delivery.areas.index');
    }

    public function service(Request $request, DeliveryCoverage $coverage): RedirectResponse
    {
        $data = $request->validate(['open' => 'required|boolean', 'reason' => 'required|string|min:3|max:500']);
        $coverage->service((bool) $data['open'], $data['reason'], DiningContext::staff($request->user()));

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupGrant;
use App\Application\Pickup\PickupOrders;
use App\Domain\Catalog\Money;
use App\Enums\OrderStatus;
use App\Enums\StaffPermission;
use App\Http\Presenters\DiningMenu;
use App\Models\CatalogProduct;
use App\Models\DiningOrder;
use App\Models\PickupAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PickupController extends Controller
{
    private function guest(Request $request): PickupContext
    {
        $grant = $request->attributes->get('pickup.grant');
        abort_unless($grant instanceof PickupGrant, 403);

        return PickupContext::guest($grant);
    }

    public function index(Request $request): Response
    {
        $request->validate(['status' => 'nullable|in:confirmed,preparing,ready,collected,cancelled']);
        $orders = DiningOrder::query()->where('modality', 'pickup')->whereIn('status', $request->input('status') ? [$request->string('status')->toString()] : ['confirmed', 'preparing', 'ready'])
            ->orderBy('created_at')->paginate(20)->withQueryString();
        $accounts = PickupAccount::query()->whereIn('id', $orders->getCollection()->pluck('pickup_account_id'))->get()->keyBy('id');

        return Inertia::render('pickup/index', ['orders' => $orders->getCollection()->map(fn ($order) => ['reference' => $order->public_id,
            'locationLabel' => $order->locationLabel(), 'status' => OrderStatus::from($order->status)->label('pickup'),
            'phone' => $accounts->get($order->pickup_account_id)?->phone, 'url' => route('operations.show', $order),
            'cashUrl' => $request->user()->hasPermission(StaffPermission::Cashier) ? route('cash.pickup.account', ['session' => $order->pickup_account_id]) : null]),
            'pagination' => ['previous' => $orders->previousPageUrl(), 'next' => $orders->nextPageUrl()], 'filter' => $request->input('status', ''),
            'receiving' => (bool) DB::table('pickup_settings')->where('id', 1)->value('accepting_orders'),
            'canManage' => $request->user()->hasPermission(StaffPermission::ManagePickup), 'serviceUrl' => route('pickup.service'),
            'menuUrl' => $request->user()->hasPermission(StaffPermission::PlacePickupOrder) ? route('pickup.menu') : null]);
    }

    public function service(Request $request, PickupOrders $orders): RedirectResponse
    {
        $data = $request->validate(['open' => 'required|boolean', 'reason' => 'required|string|min:3|max:500']);
        $orders->service((bool) $data['open'], $data['reason'], PickupContext::staff($request->user()));

        return back();
    }

    public function menu(Request $request): Response
    {
        $public = $request->routeIs('pickup.public.*');

        return Inertia::render($public ? 'public/pickup/menu' : 'pickup/menu', ['table' => null, 'heading' => 'Pedido para retirada',
            'backUrl' => $public ? '/' : route('pickup.index'), 'products' => DiningMenu::products(pickup: $public),
            'receiving' => (bool) DB::table('pickup_settings')->where('id', 1)->value('accepting_orders'),
            'previewUrl' => route($public ? 'pickup.public.preview' : 'pickup.preview'), 'callUrl' => null, 'staff' => ! $public,
            'requiresPhone' => true, 'canManualDiscount' => ! $public && $request->user()->hasPermission(StaffPermission::ManualDiscount)]);
    }

    public function preview(Request $request, PickupOrders $orders): RedirectResponse
    {
        $public = $request->routeIs('pickup.public.*');
        $intent = $orders->preview($request->all(), $public ? $this->guest($request) : PickupContext::staff($request->user()));

        return to_route($public ? 'pickup.public.review' : 'pickup.review', ['intent' => $intent->id]);
    }

    public function review(Request $request, string $intent, PickupOrders $orders): Response
    {
        $public = $request->routeIs('pickup.public.*');
        $record = $orders->intent($intent, $public ? $this->guest($request) : PickupContext::staff($request->user()));

        return Inertia::render($public ? 'public/pickup/review' : 'pickup/review', ['heading' => 'Confira o pedido para retirada', 'tableNumber' => null,
            'contactHint' => 'Contato terminado em '.substr($record->phone, -4), 'items' => DiningMenu::items($record->snapshot), 'total' => Money::label($record->total_cents),
            'expiresAt' => $record->expires_at->toIso8601String(), 'expired' => $record->expires_at <= now(),
            'confirmUrl' => route($public ? 'pickup.public.confirm' : 'pickup.confirm', ['intent' => $intent]),
            'backUrl' => route($public ? 'pickup.public.menu' : 'pickup.menu')]);
    }

    public function confirm(Request $request, string $intent, PickupOrders $orders): RedirectResponse
    {
        $public = $request->routeIs('pickup.public.*');
        $order = $orders->confirm($intent, $public ? $this->guest($request) : PickupContext::staff($request->user()));

        return $public ? to_route('pickup.public.receipt', $order->public_id) : to_route('operations.show', $order);
    }

    private function owned(Request $request, string $reference): DiningOrder
    {
        $context = $this->guest($request);
        $context->authorize(StaffPermission::PlacePickupOrder);

        return DiningOrder::query()->where('modality', 'pickup')->where('source', 'web')->where('public_id', $reference)
            ->where('client_hash', $context->grant?->clientHash())->firstOrFail();
    }

    public function receipt(Request $request, string $reference): Response
    {
        $order = $this->owned($request, $reference);

        return Inertia::render('public/pickup/receipt', ['order' => DiningMenu::order($order), 'backUrl' => route('pickup.public.menu'),
            'realtimeAuthUrl' => route('pickup.public.realtime', $reference)]);
    }

    public function realtime(Request $request, string $reference): mixed
    {
        $request->validate(['channel_name' => 'required|string', 'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/']]);
        $order = $this->owned($request, $reference);
        abort_unless($request->input('channel_name') === 'private-pickup.order.'.$order->public_id, 403);

        return Broadcast::connection('reverb')->validAuthenticationResponse($request, true);
    }

    public function photo(Request $request, CatalogProduct $product): StreamedResponse
    {
        $this->guest($request)->authorize(StaffPermission::PlacePickupOrder);
        $product->load('category');
        abort_unless($product->is_active && $product->is_reviewed && $product->category->is_active && $product->photo_path && Storage::disk('local')->exists($product->photo_path), 404);

        return Storage::disk('local')->response($product->photo_path, headers: ['X-Content-Type-Options' => 'nosniff']);
    }
}

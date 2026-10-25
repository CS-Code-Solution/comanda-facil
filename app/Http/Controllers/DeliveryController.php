<?php

namespace App\Http\Controllers;

use App\Application\Cash\BillingAccounts;
use App\Application\Delivery\DeliveryAccess;
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryDispatch;
use App\Application\Delivery\DeliveryGrant;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Dining\DiningContext;
use App\Domain\Catalog\Money;
use App\Enums\OrderStatus;
use App\Enums\StaffPermission;
use App\Http\Presenters\DiningMenu;
use App\Models\CatalogProduct;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    private function guest(Request $request): DeliveryContext
    {
        $grant = $request->attributes->get('delivery.grant');
        abort_unless($grant instanceof DeliveryGrant, 403);

        return DeliveryContext::guest($grant);
    }

    public function index(Request $request): Response
    {
        $request->validate(['status' => 'nullable|in:confirmed,preparing,ready,waiting_courier,dispatched,delivered,cancelled']);
        $all = $request->user()->hasPermission(StaffPermission::ViewDeliveries);
        $orders = DiningOrder::query()->where('modality', 'delivery')
            ->unless($all, fn ($q) => $q->whereIn('delivery_account_id', DeliveryAccount::query()->select('id')->where('courier_id', $request->user()->id)))
            ->whereIn('status', $request->input('status') ? [$request->string('status')->toString()] : ['confirmed', 'preparing', 'ready', 'waiting_courier', 'dispatched'])
            ->orderBy('created_at')->orderBy('id')->paginate(20)->withQueryString();

        return Inertia::render('delivery/index', ['orders' => $orders->getCollection()->map(fn ($order) => ['id' => $order->id,
            'label' => $order->locationLabel(), 'status' => OrderStatus::from($order->status)->label('delivery'), 'url' => route('delivery.show', $order)]),
            'pagination' => ['previous' => $orders->previousPageUrl(), 'next' => $orders->nextPageUrl()], 'filter' => $request->input('status', ''),
            'receiving' => (bool) DB::table('delivery_settings')->where('id', 1)->value('accepting_orders'),
            'adminUrl' => $request->user()->hasPermission(StaffPermission::ManageDelivery) ? route('delivery.areas.index') : null,
            'menuUrl' => $request->user()->hasPermission(StaffPermission::PlaceDeliveryOrder) ? route('delivery.menu') : null, 'all' => $all]);
    }

    public function show(Request $request, DiningOrder $order): Response
    {
        abort_unless($order->modality === 'delivery', 404);
        $user = $request->user();
        if (! $user->hasPermission(StaffPermission::ViewDeliveries)) {
            DeliveryAccess::courier($order, DiningContext::staff($user));
        }
        $account = DeliveryAccount::query()->findOrFail($order->delivery_account_id);
        $assigned = $account->courier_id === $user->id && $user->role === 'entregador';
        $dispatch = $user->hasPermission(StaffPermission::DispatchDelivery);
        $next = OrderStatus::from($order->status)->next('delivery');
        $canNext = $next && match ($next) {
            OrderStatus::Preparing, OrderStatus::Ready => $user->hasPermission(StaffPermission::PrepareOrder),
            OrderStatus::WaitingCourier => $dispatch,
            OrderStatus::Dispatched, OrderStatus::Delivered => $assigned,
            default => false,
        };

        return Inertia::render('delivery/show', ['order' => DiningMenu::order($order), 'version' => $order->version,
            'contact' => $dispatch || $assigned ? ['phone' => $account->phone, 'address' => $account->address] : null,
            'courier' => $account->courier_id ? User::query()->where('id', $account->courier_id)->value('name') : null,
            'couriers' => $dispatch ? User::query()->where('role', 'entregador')->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
            'canAssign' => $dispatch && ! $account->settled_at && in_array($order->status, ['ready', 'waiting_courier', 'dispatched'], true),
            'assignUrl' => route('delivery.assign', $order), 'assignOperation' => (string) Str::uuid(),
            'next' => $canNext ? ['status' => $next->value, 'label' => $next->label('delivery')] : null,
            'commandUrl' => route('operations.transition', $order), 'operationId' => (string) Str::uuid(),
            'cashUrl' => $assigned || $user->hasPermission(StaffPermission::Cashier) ? BillingAccounts::route($account) : null,
            'operationsUrl' => $user->hasPermission(StaffPermission::ViewDeliveries) ? route('operations.show', $order) : null]);
    }

    public function assign(Request $request, DiningOrder $order, DeliveryDispatch $dispatch): RedirectResponse
    {
        $dispatch->assign($order->id, $request->all(), DiningContext::staff($request->user()));

        return back();
    }

    public function menu(Request $request): Response
    {
        $public = $request->routeIs('delivery.public.*');

        return Inertia::render($public ? 'public/delivery/menu' : 'delivery/menu', ['table' => null, 'heading' => 'Pedido para entrega',
            'backUrl' => $public ? '/' : route('delivery.index'), 'products' => DiningMenu::products(delivery: $public),
            'receiving' => (bool) DB::table('delivery_settings')->where('id', 1)->value('accepting_orders'),
            'previewUrl' => route($public ? 'delivery.public.preview' : 'delivery.preview'), 'callUrl' => null, 'staff' => ! $public,
            'requiresPhone' => true, 'requiresAddress' => true, 'canManualDiscount' => ! $public && $request->user()->hasPermission(StaffPermission::ManualDiscount)]);
    }

    public function preview(Request $request, DeliveryOrders $orders): RedirectResponse
    {
        $public = $request->routeIs('delivery.public.*');
        $intent = $orders->preview($request->all(), $public ? $this->guest($request) : DeliveryContext::staff($request->user()));

        return to_route($public ? 'delivery.public.review' : 'delivery.review', ['intent' => $intent->id]);
    }

    public function review(Request $request, string $intent, DeliveryOrders $orders): Response
    {
        $public = $request->routeIs('delivery.public.*');
        $record = $orders->intent($intent, $public ? $this->guest($request) : DeliveryContext::staff($request->user()));

        return Inertia::render($public ? 'public/delivery/review' : 'delivery/review', ['heading' => 'Confira o pedido para entrega', 'tableNumber' => null,
            'contactHint' => 'Contato terminado em '.substr($record->phone, -4), 'items' => DiningMenu::items($record->snapshot), 'total' => Money::label($record->total_cents),
            'delivery' => ['address' => $record->address, 'area' => $record->coverage['name'], 'fee' => Money::label($record->coverage['fee_cents']),
                'minimum' => Money::label($record->coverage['minimum_cents']), 'itemsTotal' => Money::label($record->total_cents - $record->coverage['fee_cents'])],
            'expiresAt' => $record->expires_at->toIso8601String(), 'expired' => $record->expires_at <= now(),
            'confirmUrl' => route($public ? 'delivery.public.confirm' : 'delivery.confirm', ['intent' => $intent]),
            'backUrl' => route($public ? 'delivery.public.menu' : 'delivery.menu')]);
    }

    public function confirm(Request $request, string $intent, DeliveryOrders $orders): RedirectResponse
    {
        $public = $request->routeIs('delivery.public.*');
        $order = $orders->confirm($intent, $public ? $this->guest($request) : DeliveryContext::staff($request->user()));

        return $public ? to_route('delivery.public.receipt', $order->public_id) : to_route('delivery.show', $order);
    }

    private function owned(Request $request, string $reference): DiningOrder
    {
        $context = $this->guest($request);
        $context->authorize(StaffPermission::PlaceDeliveryOrder);

        return DiningOrder::query()->where('modality', 'delivery')->where('source', 'web')->where('public_id', $reference)
            ->where('client_hash', $context->grant?->clientHash())->firstOrFail();
    }

    public function receipt(Request $request, string $reference): Response
    {
        $order = $this->owned($request, $reference);

        return Inertia::render('public/delivery/receipt', ['order' => DiningMenu::order($order), 'backUrl' => route('delivery.public.menu'),
            'realtimeAuthUrl' => route('delivery.public.realtime', $reference)]);
    }

    public function realtime(Request $request, string $reference): mixed
    {
        $request->validate(['channel_name' => 'required|string', 'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/']]);
        $order = $this->owned($request, $reference);
        abort_unless($request->input('channel_name') === 'private-delivery.order.'.$order->public_id, 403);

        return Broadcast::connection('reverb')->validAuthenticationResponse($request, true);
    }

    public function photo(Request $request, CatalogProduct $product): StreamedResponse
    {
        $this->guest($request)->authorize(StaffPermission::PlaceDeliveryOrder);
        $product->load('category');
        abort_unless($product->is_active && $product->is_reviewed && $product->category->is_active && $product->photo_path && Storage::disk('local')->exists($product->photo_path), 404);

        return Storage::disk('local')->response($product->photo_path, headers: ['X-Content-Type-Options' => 'nosniff']);
    }
}

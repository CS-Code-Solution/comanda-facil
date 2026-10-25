<?php

namespace App\Http\Controllers;

use App\Application\Cash\BillingAccounts;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderFulfillment;
use App\Domain\Catalog\Money;
use App\Enums\OrderStatus;
use App\Enums\StaffPermission;
use App\Http\Presenters\DiningMenu;
use App\Models\CashPayment;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use App\Models\PickupAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderOperationsController extends Controller
{
    public function kitchen(Request $request): Response
    {
        $request->validate(['status' => 'nullable|in:confirmed,preparing,ready,served,collected,waiting_courier,dispatched,delivered,cancelled', 'modality' => 'nullable|in:dining_room,pickup,delivery']);
        $orders = DiningOrder::query()->with(['items', 'session'])->whereIn('status', $request->input('status') ? [$request->string('status')->toString()] : ['confirmed', 'preparing', 'ready'])
            ->when($request->input('modality'), fn ($q, $mode) => $q->where('modality', $mode))->orderBy('created_at')->orderBy('id')->paginate(24)->withQueryString();

        return Inertia::render('operations/kitchen', ['orders' => $orders->getCollection()->map(fn (DiningOrder $order) => $this->present($order, $request)),
            'pagination' => ['previous' => $orders->previousPageUrl(), 'next' => $orders->nextPageUrl()], 'filter' => $request->input('status', ''), 'modality' => $request->input('modality', ''),
            'cancellations' => DiningOrder::query()->where('status', 'cancelled')->where('cancelled_at', '>=', now()->subHours(2))
                ->orderByDesc('cancelled_at')->limit(10)->get()->map(fn ($order) => ['reference' => $order->public_id, 'table' => $order->table_number, 'locationLabel' => $order->locationLabel(), 'url' => route('operations.show', $order)])]);
    }

    public function show(Request $request, DiningOrder $order): Response
    {
        $this->access($request, $order);
        $commercial = $request->user()->hasPermission($this->commercialPermission($order));
        $history = DB::table('order_history')->leftJoin('users', 'users.id', '=', 'order_history.actor_id')
            ->where('order_id', $order->id)->orderByDesc('order_history.id')->paginate(10, ['order_history.*', 'users.name as actor'])->withQueryString();

        return Inertia::render('operations/order', ['order' => $this->present($order, $request),
            'history' => $commercial ? $history->getCollection()->map(fn ($row) => ['version' => $row->version, 'action' => $row->action === 'amended' ? 'Correção de itens' : OrderStatus::from($row->action)->label($order->modality),
                'actor' => $row->actor ?? ($order->modality === 'pickup' ? 'Cliente online' : 'QR do cliente'), 'reason' => $row->reason, 'createdAt' => $row->created_at,
                'before' => $this->historySnapshot($row->before, $order->modality), 'after' => $this->historySnapshot($row->after, $order->modality)]) : [],
            'pagination' => ['previous' => $history->previousPageUrl(), 'next' => $history->nextPageUrl()],
            'phone' => $commercial && $order->modality === 'pickup' ? PickupAccount::findOrFail($order->pickup_account_id)->phone : null,
            'cashUrl' => ($request->user()->hasPermission(StaffPermission::Cashier) || ($order->modality === 'dining_room' && $request->user()->hasPermission(StaffPermission::ReceivePayment))) ? BillingAccounts::route(BillingAccounts::forOrder($order)) : null,
            'printsUrl' => $request->user()->hasPermission(StaffPermission::PrintCashier) || $request->user()->hasPermission(StaffPermission::PrintKitchen) ? route('printing.index', ['order' => $order->id]) : null,
            'legacyPrintUrl' => $request->user()->hasPermission(StaffPermission::PrintCashier) ? route('printing.legacy', $order) : null]);
    }

    /** @return array<string, mixed> */
    private function historySnapshot(string $json, string $modality): array
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $data === [] ? [] : ['status' => OrderStatus::from($data['status'])->label($modality),
            'total' => Money::label($data['total_cents']), 'items' => DiningMenu::items($data['items'])];
    }

    /** @return array<string, mixed> */
    private function present(DiningOrder $order, Request $request): array
    {
        $user = $request->user();
        $commercial = $user->hasPermission($this->commercialPermission($order));
        $account = BillingAccounts::forOrder($order);
        $status = OrderStatus::from($order->status);
        $next = $status->next($order->modality);
        $canNext = $next && $user->hasPermission(match ($next) {
            OrderStatus::Served => StaffPermission::ServeOrder, OrderStatus::Collected => StaffPermission::CollectPickup, OrderStatus::WaitingCourier => StaffPermission::DispatchDelivery, OrderStatus::Dispatched, OrderStatus::Delivered => StaffPermission::ReceiveDeliveryPayment, default => StaffPermission::PrepareOrder
        });
        if (in_array($next, [OrderStatus::Dispatched, OrderStatus::Delivered], true)) {
            $canNext = false;
        }
        $canCancel = $status !== OrderStatus::Cancelled && $user->hasPermission($order->started_at ? StaffPermission::CancelPreparedOrder : StaffPermission::CancelOrder);
        if ($account->closed_at) {
            $canCancel = $canCancel && $user->hasPermission(StaffPermission::RefundPayment) && CashPayment::query()->where(BillingAccounts::column($order->modality), $account->id)->exists();
        }
        $order->loadMissing('items');
        $items = DiningMenu::items(array_values($order->items->map(fn ($item) => $item->snapshot)->all()));
        if (! $commercial) {
            $items = array_map(fn ($item) => array_intersect_key($item, array_flip(['name', 'presentation', 'quantity', 'note', 'options'])), $items);
        }

        return ['id' => $order->id, 'reference' => $order->public_id, 'tableNumber' => $order->table_number, 'locationLabel' => $order->locationLabel(), 'modality' => $order->modality,
            'version' => $order->version, 'revision' => $order->content_revision, 'status' => $status->label($order->modality), 'createdAt' => $order->created_at->toIso8601String(),
            'total' => $commercial ? Money::label($order->status === 'cancelled' ? 0 : $order->total_cents + ($account instanceof DeliveryAccount ? $account->fee_cents : 0)) : null,
            'deliveryFee' => $commercial && $account instanceof DeliveryAccount ? Money::label($order->status === 'cancelled' ? 0 : $account->fee_cents) : null, 'items' => $items,
            'next' => $canNext ? ['status' => $next->value, 'label' => $next->label($order->modality), 'operation_id' => (string) Str::uuid()] : null,
            'cancelOperation' => $canCancel ? (string) Str::uuid() : null, 'commandUrl' => route('operations.transition', $order),
            'url' => route('operations.show', $order), 'editUrl' => $status === OrderStatus::Confirmed && ! $account->closed_at && $commercial && $user->hasPermission(StaffPermission::AmendOrder) ? route('operations.edit', $order) : null];
    }

    public function transition(Request $request, DiningOrder $order, OrderFulfillment $fulfillment): RedirectResponse
    {
        $fulfillment->transition($order->id, $request->all(), DiningContext::staff($request->user()));

        return back();
    }

    public function edit(Request $request, DiningOrder $order): Response
    {
        DiningContext::staff($request->user())->authorize(StaffPermission::AmendOrder);
        $this->access($request, $order);
        abort_unless($request->user()->hasPermission($this->commercialPermission($order)), 403);
        $account = BillingAccounts::forOrder($order);
        abort_if($account->closed_at !== null, 409);
        abort_unless($order->status === 'confirmed', 409);
        $items = $order->items()->get()->map(function ($item): array {
            $snapshot = $item->snapshot;
            $options = [];
            foreach ($snapshot['options'] as $option) {
                $options[(string) $option['id']] = (string) $option['quantity'];
            }

            return ['label' => $snapshot['name'].' / '.$snapshot['variant_name'], 'input' => [
                'variant_id' => (string) $item->variant_id, 'second_variant_id' => (string) ($snapshot['flavors'][1]['variant_id'] ?? ''),
                'quantity' => (string) $item->quantity, 'options' => (object) $options, 'note' => $item->note ?? '',
                'manual_discount' => ($snapshot['applied_discount']['source'] ?? null) === 'manual'
                    ? ['type' => $snapshot['applied_discount']['type'], 'value' => Money::decimal($snapshot['applied_discount']['value'])] : null]];
        });

        return Inertia::render('operations/edit', ['heading' => $order->modality !== 'dining_room' ? 'Corrigir '.$order->locationLabel() : null, 'backUrl' => route('operations.show', $order), 'table' => $order->modality !== 'dining_room' ? null : ['number' => $order->table_number, 'label' => 'Corrigir pedido '.$order->public_id, 'cycle' => $order->table()->firstOrFail()->cycle],
            'products' => DiningMenu::products(), 'receiving' => true, 'staff' => true, 'callUrl' => null,
            'canManualDiscount' => $request->user()->hasPermission(StaffPermission::ManualDiscount), 'previewUrl' => route('operations.preview', $order),
            'initialItems' => $items, 'changeVersion' => $order->version]);
    }

    public function preview(Request $request, DiningOrder $order, OrderFulfillment $fulfillment): RedirectResponse
    {
        $id = $fulfillment->preview($order->id, $request->all(), DiningContext::staff($request->user()));

        return to_route('operations.review', ['order' => $order->id, 'intent' => $id]);
    }

    public function review(Request $request, DiningOrder $order, string $intent, OrderFulfillment $fulfillment): Response
    {
        $record = $fulfillment->changeIntent($order->id, $intent, DiningContext::staff($request->user()));
        $snapshot = json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $account = BillingAccounts::forOrder($order);
        $fee = $account instanceof DeliveryAccount ? $account->fee_cents : 0;

        return Inertia::render('operations/review', ['heading' => $order->modality !== 'dining_room' ? 'Confira a correção · '.$order->locationLabel() : null, 'tableNumber' => $order->table_number, 'items' => DiningMenu::items($snapshot),
            'total' => Money::label((int) array_sum(array_column($snapshot, 'total_cents')) + $fee),
            'additionalFee' => $account instanceof DeliveryAccount ? Money::label($fee) : null, 'reason' => $record->reason,
            'expiresAt' => $record->expires_at, 'expired' => now()->greaterThanOrEqualTo($record->expires_at),
            'confirmUrl' => route('operations.confirm', ['order' => $order->id, 'intent' => $intent]), 'backUrl' => route('operations.edit', $order)]);
    }

    public function confirm(Request $request, DiningOrder $order, string $intent, OrderFulfillment $fulfillment): RedirectResponse
    {
        $fulfillment->confirm($order->id, $intent, DiningContext::staff($request->user()));

        return to_route('operations.show', $order);
    }

    private function commercialPermission(DiningOrder $order): StaffPermission
    {
        return match ($order->modality) {
            'pickup' => StaffPermission::ViewPickup, 'delivery' => StaffPermission::ViewDeliveries, default => StaffPermission::DiningRoom
        };
    }

    private function access(Request $request, DiningOrder $order): void
    {
        abort_unless($request->user()->hasPermission(StaffPermission::Kitchen) || $request->user()->hasPermission($this->commercialPermission($order)), 403);
    }
}

<?php

namespace App\Application\Orders;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\BillingAccounts;
use App\Application\Dining\DiningContext;
use App\Application\Pickup\PickupContext;
use App\Enums\OrderStatus;
use App\Enums\StaffPermission;
use App\Models\CashPayment;
use App\Models\DiningOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class OrderFulfillment
{
    public function __construct(private OrderPricing $pricing, private OrderPrinting $printing, private AccountBalance $balance) {}

    public function created(DiningOrder $order, DiningContext|PickupContext $context): void
    {
        $this->history($order, $context, 'confirmed', null, []);
        $this->printing->enqueue($order, 'original', actorId: $context->actorId);
        $this->balance->refreshRefundFlag(BillingAccounts::forOrder($order));
        OperationsChanges::record($order->public_id);
    }

    /** @return array<string, mixed> */
    public function snapshot(DiningOrder $order): array
    {
        return ['status' => $order->status, 'total_cents' => $order->total_cents, 'content_revision' => $order->content_revision,
            'items' => $order->items()->get()->map(fn ($item) => $item->snapshot)->all()];
    }

    /** @param array<string, mixed> $input */
    public function transition(int $id, array $input, DiningContext $context): DiningOrder
    {
        $data = Validator::make($input, ['operation_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'status' => 'required|in:preparing,ready,served,collected,cancelled', 'reason' => 'required_if:status,cancelled|nullable|string|min:3|max:500'])->validate();

        return DB::transaction(function () use ($id, $data, $context): DiningOrder {
            $order = $this->lock($id);
            $target = OrderStatus::from($data['status']);
            $account = BillingAccounts::forOrder($order);
            if ($order->modality === 'pickup' && ! in_array($target, [OrderStatus::Preparing, OrderStatus::Ready], true)) {
                $context->authorize(StaffPermission::ViewPickup);
            }
            $permission = $target === OrderStatus::Cancelled
                ? ($order->started_at ? StaffPermission::CancelPreparedOrder : StaffPermission::CancelOrder)
                : ($target === OrderStatus::Collected ? StaffPermission::CollectPickup : ($target === OrderStatus::Served ? StaffPermission::ServeOrder : StaffPermission::PrepareOrder));
            $context->authorize($permission);
            $fingerprint = hash('sha256', json_encode([$id, (int) $data['version'], $data['status'], $data['reason'] ?? null], JSON_THROW_ON_ERROR));
            if ($this->replayed($data['operation_id'], $order, $context, $fingerprint)) {
                return $order;
            }
            $this->current($order, (int) $data['version']);
            $status = OrderStatus::from($order->status);
            if ($status === OrderStatus::Cancelled || ($target !== OrderStatus::Cancelled && $status->next($order->modality) !== $target)) {
                throw ValidationException::withMessages(['operation' => 'Transição inválida. Recarregue o pedido; etapas não podem ser saltadas.']);
            }
            if ($target === OrderStatus::Cancelled && $account->closed_at) {
                $context->authorize(StaffPermission::RefundPayment);
                if (! CashPayment::query()->where(BillingAccounts::column(BillingAccounts::kind($account)), $account->id)->exists()) {
                    throw ValidationException::withMessages(['operation' => 'Este atendimento já foi encerrado.']);
                }
            }
            if ($target === OrderStatus::Cancelled) {
                $this->balance->guardPaidChange($order, [], true);
            }
            if ($target === OrderStatus::Collected) {
                $balance = $this->balance->forAccount($account);
                if (! $account->settled_at || ! $balance['payment'] || $balance['refund_due_cents'] > 0 || $balance['payment']->amount_cents - $balance['refunded_cents'] < $balance['total_cents']) {
                    throw ValidationException::withMessages(['operation' => 'Registre o pagamento integral e resolva devoluções antes de liberar a retirada.']);
                }
                $account->forceFill(['closed_at' => now(), 'closed_by_id' => $context->actorId])->save();
            }
            $before = $this->snapshot($order);
            $order->status = $target->value;
            $order->status_changed_at = now();
            $column = match ($target) {
                OrderStatus::Preparing => 'started_at', OrderStatus::Ready => 'ready_at',
                OrderStatus::Served => 'served_at', OrderStatus::Collected => 'collected_at', OrderStatus::Cancelled => 'cancelled_at', default => null,
            };
            $order->setAttribute($column, now());
            $order->version++;
            if ($target === OrderStatus::Cancelled) {
                $order->content_revision++;
            }
            $order->save();
            if ($order->modality === 'pickup' && $target === OrderStatus::Cancelled && ! $account->closed_at) {
                $account->forceFill(['closed_at' => now(), 'closed_by_id' => $context->actorId])->save();
            }
            $this->history($order, $context, $target->value, $data['reason'] ?? null, $before);
            if ($target === OrderStatus::Cancelled) {
                $this->printing->enqueue($order, 'cancellation', $before, $context->actorId);
            }
            $this->remember($data['operation_id'], $order, $context, $fingerprint);
            $this->balance->refreshRefundFlag(BillingAccounts::forOrder($order));
            OperationsChanges::record($order->public_id);

            return $order;
        });
    }

    /** @param array<string, mixed> $input */
    public function preview(int $id, array $input, DiningContext $context): string
    {
        $data = Validator::make($input, ['version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:500',
            'items' => 'required|array|min:1|max:20', 'items.*' => 'required|array:variant_id,second_variant_id,quantity,options,manual_discount,note',
            'items.*.note' => 'nullable|string|max:500'])->validate();

        return DB::transaction(function () use ($id, $data, $context): string {
            $order = $this->lock($id);
            $context->authorize(StaffPermission::AmendOrder);
            if ($order->modality === 'pickup') {
                $context->authorize(StaffPermission::ViewPickup);
            }
            $this->editable($order, (int) $data['version']);
            $snapshot = $this->pricing->quoteItems($data['items'], $context);
            $this->balance->guardPaidChange($order, $snapshot);
            $uuid = (string) Str::uuid();
            DB::table('order_change_intents')->insert(['id' => $uuid, 'order_id' => $id, 'actor_id' => $context->actorId,
                'auth_version' => $context->authVersion, 'expected_version' => $order->version, 'reason' => $data['reason'],
                'request' => json_encode($data['items'], JSON_THROW_ON_ERROR), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'expires_at' => now()->addMinutes(10)]);

            return $uuid;
        });
    }

    public function changeIntent(int $id, string $uuid, DiningContext $context): stdClass
    {
        $context->authorize(StaffPermission::AmendOrder);
        if (DiningOrder::query()->findOrFail($id)->modality === 'pickup') {
            $context->authorize(StaffPermission::ViewPickup);
        }
        $intent = DB::table('order_change_intents')->where('id', $uuid)->where('order_id', $id)->where('actor_id', $context->actorId)->where('auth_version', $context->authVersion)->first();
        abort_unless($intent !== null, 404);

        return $intent;
    }

    public function confirm(int $id, string $uuid, DiningContext $context): DiningOrder
    {
        return DB::transaction(function () use ($id, $uuid, $context): DiningOrder {
            $order = $this->lock($id);
            $intent = $this->changeIntent($id, $uuid, $context);
            $fingerprint = hash('sha256', 'amend:'.$uuid);
            if ($this->replayed($uuid, $order, $context, $fingerprint)) {
                return $order;
            }
            $this->editable($order, $intent->expected_version);
            if (now()->greaterThanOrEqualTo($intent->expires_at)) {
                throw ValidationException::withMessages(['operation' => 'A prévia expirou. Faça uma nova prévia.']);
            }
            $snapshot = $this->pricing->quoteItems(json_decode($intent->request, true, flags: JSON_THROW_ON_ERROR), $context);
            if ($this->pricing->canonical($snapshot) !== $this->pricing->canonical(json_decode($intent->snapshot, true, flags: JSON_THROW_ON_ERROR))) {
                throw ValidationException::withMessages(['operation' => 'O preço ou a configuração mudou. Faça uma nova prévia.']);
            }
            $total = (int) array_sum(array_column($snapshot, 'total_cents'));
            $this->balance->guardPaidChange($order, $snapshot);
            $before = $this->snapshot($order);
            $order->items()->delete();
            foreach ($snapshot as $position => $item) {
                $order->items()->make()->forceFill(['position' => $position, 'product_id' => $item['product_id'], 'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'], 'note' => $item['note'], 'snapshot' => $item, 'total_cents' => $item['total_cents']])->save();
            }
            $order->total_cents = $total;
            $order->version++;
            $order->content_revision++;
            $order->save();
            $this->history($order, $context, 'amended', $intent->reason, $before);
            $this->printing->enqueue($order, 'amendment', $before, $context->actorId);
            $this->remember($uuid, $order, $context, $fingerprint);
            $this->balance->refreshRefundFlag(BillingAccounts::forOrder($order));
            OperationsChanges::record($order->public_id);

            return $order;
        });
    }

    private function lock(int $id): DiningOrder
    {
        return OrderAccess::lock($id);
    }

    private function current(DiningOrder $order, int $version): void
    {
        if ($order->version !== $version) {
            throw ValidationException::withMessages(['operation' => 'O pedido mudou em outro dispositivo. Recarregue antes de tentar novamente.']);
        }
    }

    private function editable(DiningOrder $order, int $version): void
    {
        $account = BillingAccounts::forOrder($order);
        $this->current($order, $version);
        if ($order->status !== 'confirmed' || $order->started_at || $account->closed_at) {
            throw ValidationException::withMessages(['operation' => 'Somente pedidos confirmados, antes do preparo e em atendimento aberto, podem ser corrigidos.']);
        }
    }

    /** @param array<string, mixed> $before */
    private function history(DiningOrder $order, DiningContext|PickupContext $context, string $action, ?string $reason, array $before): void
    {
        DB::table('order_history')->insert(['order_id' => $order->id, 'version' => $order->version, 'actor_id' => $context->actorId,
            'action' => $action, 'reason' => $reason, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($this->snapshot($order), JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function replayed(string $uuid, DiningOrder $order, DiningContext $context, string $fingerprint): bool
    {
        $operation = DB::table('order_operations')->where('id', $uuid)->first();
        if (! $operation) {
            return false;
        }
        if ($operation->order_id !== $order->id || $operation->actor_id !== $context->actorId || ! hash_equals($operation->fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['operation' => 'Identificador de operação já utilizado. Recarregue a página.']);
        }

        return true;
    }

    private function remember(string $uuid, DiningOrder $order, DiningContext $context, string $fingerprint): void
    {
        DB::table('order_operations')->insert(['id' => $uuid, 'order_id' => $order->id, 'actor_id' => $context->actorId,
            'fingerprint' => $fingerprint, 'result_version' => $order->version, 'created_at' => now()]);
    }
}

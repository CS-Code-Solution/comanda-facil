<?php

namespace App\Application\Delivery;

use App\Application\Dining\DiningContext;
use App\Application\Orders\OperationsChanges;
use App\Application\Orders\OrderAccess;
use App\Enums\StaffPermission;
use App\Models\DeliveryAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryDispatch
{
    /** @param array<string, mixed> $input */
    public function assign(int $id, array $input, DiningContext $context): void
    {
        $data = Validator::make($input, ['courier_id' => 'required|integer|min:1', 'operation_id' => 'required|uuid',
            'version' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:500'])->validate();
        DB::transaction(function () use ($id, $data, $context): void {
            $order = OrderAccess::lock($id);
            $context->authorize(StaffPermission::DispatchDelivery);
            abort_unless($order->modality === 'delivery', 404);
            $fingerprint = hash('sha256', json_encode([$id, $data], JSON_THROW_ON_ERROR));
            $previous = DB::table('delivery_events')->where('operation_id', $data['operation_id'])->first();
            if ($previous) {
                abort_unless($previous->actor_id === $context->actorId && hash_equals($previous->fingerprint, $fingerprint), 403);

                return;
            }
            $account = DeliveryAccount::query()->findOrFail($order->delivery_account_id);
            $courier = User::query()->where('id', $data['courier_id'])->sharedLock()->firstOrFail();
            if ($order->version !== (int) $data['version'] || ! in_array($order->status, ['ready', 'waiting_courier', 'dispatched'], true)
                || $account->settled_at || ! $courier->isActiveStaff() || $courier->role !== 'entregador') {
                throw ValidationException::withMessages(['delivery' => 'Recarregue: atribuição exige pedido pronto/em entrega sem pagamento e entregador ativo.']);
            }
            $old = $account->courier_id;
            $account->forceFill(['courier_id' => $courier->id])->save();
            $order->increment('version');
            DB::table('delivery_events')->insert(['order_id' => $id, 'actor_id' => $context->actorId, 'action' => 'courier_assigned',
                'operation_id' => $data['operation_id'], 'fingerprint' => $fingerprint,
                'details' => json_encode(['before' => $old, 'after' => $courier->id, 'reason' => $data['reason']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            OperationsChanges::record($order->public_id);
        });
    }
}

<?php

namespace App\Application\Delivery;

use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeliveryAccess
{
    public static function receive(int $id, DiningContext $context): void
    {
        $context->authorize(StaffPermission::ReceiveDeliveryPayment);
        $account = DeliveryAccount::query()->findOrFail($id);
        $order = $account->orders()->sole();
        self::courier($order, $context);
        if ($account->courier_id !== $context->actorId || ! in_array($order->status, ['dispatched', 'delivered'], true)) {
            throw new AuthorizationException('Somente o entregador atribuído pode receber este pedido após a saída.');
        }
    }

    public static function courier(DiningOrder $order, DiningContext $context): void
    {
        $context->authorize(StaffPermission::Delivery);
        $account = DeliveryAccount::query()->findOrFail($order->delivery_account_id);
        $user = User::query()->where('id', $context->actorId)->sharedLock()->firstOrFail();
        if ($account->courier_id !== $context->actorId || $user->role !== 'entregador') {
            throw new AuthorizationException;
        }
    }
}

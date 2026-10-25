<?php

namespace App\Application\Cash;

use App\Application\Dining\DiningAccess;
use App\Models\CashPayment;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Illuminate\Validation\ValidationException;

class BillingAccounts
{
    public static function column(string $kind): string
    {
        return match ($kind) {
            'dining_room' => 'session_id', 'pickup' => 'pickup_account_id', 'delivery' => 'delivery_account_id',
            default => throw ValidationException::withMessages(['cash' => 'Modalidade de conta inválida.']),
        };
    }

    public static function kind(DiningSession|PickupAccount|DeliveryAccount $account): string
    {
        return match (true) {
            $account instanceof PickupAccount => 'pickup', $account instanceof DeliveryAccount => 'delivery', default => 'dining_room'
        };
    }

    public static function find(int $id, string $kind = 'dining_room'): DiningSession|PickupAccount|DeliveryAccount
    {
        self::column($kind);

        return match ($kind) {
            'pickup' => PickupAccount::query()->findOrFail($id), 'delivery' => DeliveryAccount::query()->findOrFail($id), default => DiningSession::query()->findOrFail($id)
        };
    }

    public static function lock(int $id, string $kind = 'dining_room'): DiningSession|PickupAccount|DeliveryAccount
    {
        $account = self::find($id, $kind);
        if ($account instanceof DiningSession) {
            DiningAccess::lockTable($account->table_id);

            return DiningSession::query()->lockForUpdate()->findOrFail($id);
        }

        return $kind === 'delivery' ? DeliveryAccount::query()->lockForUpdate()->findOrFail($id) : PickupAccount::query()->lockForUpdate()->findOrFail($id);
    }

    public static function forOrder(DiningOrder $order): DiningSession|PickupAccount|DeliveryAccount
    {
        return self::find((int) ($order->delivery_account_id ?? $order->pickup_account_id ?? $order->session_id), $order->modality);
    }

    public static function forPayment(CashPayment $payment): DiningSession|PickupAccount|DeliveryAccount
    {
        return self::find((int) ($payment->delivery_account_id ?? $payment->pickup_account_id ?? $payment->session_id),
            $payment->delivery_account_id !== null ? 'delivery' : ($payment->pickup_account_id !== null ? 'pickup' : 'dining_room'));
    }

    public static function label(DiningSession|PickupAccount|DeliveryAccount $account): string
    {
        return match (true) {
            $account instanceof PickupAccount => 'Retirada · Conta #'.$account->id, $account instanceof DeliveryAccount => 'Entrega · Conta #'.$account->id, default => 'Mesa '.$account->table->number.' · Conta #'.$account->id
        };
    }

    public static function route(DiningSession|PickupAccount|DeliveryAccount $account, string $action = 'account', ?string $intent = null): string
    {
        return route('cash.'.match (true) {
            $account instanceof PickupAccount => 'pickup.', $account instanceof DeliveryAccount => 'delivery.', default => ''
        }.$action,
            array_filter(['session' => $account->id, 'intent' => $intent], fn ($value) => $value !== null));
    }
}

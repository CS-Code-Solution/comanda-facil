<?php

namespace App\Application\Cash;

use App\Application\Dining\DiningAccess;
use App\Models\CashPayment;
use App\Models\DiningOrder;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Illuminate\Validation\ValidationException;

class BillingAccounts
{
    public static function column(string $kind): string
    {
        return match ($kind) {
            'dining_room' => 'session_id', 'pickup' => 'pickup_account_id',
            default => throw ValidationException::withMessages(['cash' => 'Modalidade de conta inválida.']),
        };
    }

    public static function kind(DiningSession|PickupAccount $account): string
    {
        return $account instanceof PickupAccount ? 'pickup' : 'dining_room';
    }

    public static function find(int $id, string $kind = 'dining_room'): DiningSession|PickupAccount
    {
        self::column($kind);

        return $kind === 'pickup' ? PickupAccount::query()->findOrFail($id) : DiningSession::query()->findOrFail($id);
    }

    public static function lock(int $id, string $kind = 'dining_room'): DiningSession|PickupAccount
    {
        $account = self::find($id, $kind);
        if ($account instanceof DiningSession) {
            DiningAccess::lockTable($account->table_id);

            return DiningSession::query()->lockForUpdate()->findOrFail($id);
        }

        return PickupAccount::query()->lockForUpdate()->findOrFail($id);
    }

    public static function forOrder(DiningOrder $order): DiningSession|PickupAccount
    {
        return $order->modality === 'pickup'
            ? PickupAccount::query()->where('id', $order->pickup_account_id)->firstOrFail()
            : DiningSession::query()->where('id', $order->session_id)->firstOrFail();
    }

    public static function forPayment(CashPayment $payment): DiningSession|PickupAccount
    {
        return $payment->pickup_account_id !== null
            ? PickupAccount::query()->where('id', $payment->pickup_account_id)->firstOrFail()
            : DiningSession::query()->where('id', $payment->session_id)->firstOrFail();
    }

    public static function label(DiningSession|PickupAccount $account): string
    {
        return $account instanceof PickupAccount ? 'Retirada · Conta #'.$account->id : 'Mesa '.$account->table->number.' · Conta #'.$account->id;
    }

    public static function route(DiningSession|PickupAccount $account, string $action = 'account', ?string $intent = null): string
    {
        return route('cash.'.($account instanceof PickupAccount ? 'pickup.' : '').$action,
            array_filter(['session' => $account->id, 'intent' => $intent], fn ($value) => $value !== null));
    }
}

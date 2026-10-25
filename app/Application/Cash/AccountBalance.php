<?php

namespace App\Application\Cash;

use App\Models\CashPayment;
use App\Models\DeliveryAccount;
use App\Models\DiningOrder;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountBalance
{
    public function __construct(private AccountPricing $pricing) {}

    /** @return array<string, mixed> */
    public function forAccount(DiningSession|PickupAccount|DeliveryAccount $session): array
    {
        $payment = CashPayment::query()->where(BillingAccounts::column(BillingAccounts::kind($session)), $session->id)->first();
        $quote = $this->pricing->quote($session, $payment?->snapshot['policy'] ?? null);
        $refunded = $payment ? (int) DB::table('cash_refunds')->where('payment_id', $payment->id)->sum('amount_cents') : 0;

        return ['payment' => $payment, 'quote' => $quote, 'total_cents' => $quote['total_cents'], 'refunded_cents' => $refunded,
            'refund_due_cents' => $payment ? max(0, $payment->amount_cents - $quote['total_cents'] - $refunded) : 0,
            'legacy' => $session->settled_at !== null && $payment === null];
    }

    /** @return array<string, mixed> */
    public function forSession(DiningSession $session): array
    {
        return $this->forAccount($session);
    }

    /** @param list<array<string, mixed>> $replacement */
    public function guardPaidChange(DiningOrder $order, array $replacement, bool $cancel = false): void
    {
        $account = BillingAccounts::forOrder($order);
        if (! $account->settled_at) {
            return;
        }
        $payment = CashPayment::query()->where(BillingAccounts::column(BillingAccounts::kind($account)), $account->id)->first();
        if (! $payment) {
            throw ValidationException::withMessages(['operation' => 'Quitação legada sem registro financeiro. Concilie o pagamento/estorno antes de alterar valores.']);
        }
        $total = $cancel ? 0 : (int) array_sum(array_column($replacement, 'total_cents'));
        if ($total > $order->total_cents) {
            throw ValidationException::withMessages(['operation' => 'Não é permitido aumentar um pedido já pago.']);
        }
        $current = $this->pricing->quote($account, $payment->snapshot['policy']);
        $next = $this->pricing->quote($account, $payment->snapshot['policy'], $order->id, $replacement, $cancel);
        $refunded = (int) DB::table('cash_refunds')->where('payment_id', $payment->id)->sum('amount_cents');
        if ($next['total_cents'] > $current['total_cents'] || $next['total_cents'] > $payment->amount_cents - $refunded) {
            throw ValidationException::withMessages(['operation' => 'Esta alteração aumentaria o valor financeiro de uma conta já paga.']);
        }
    }

    public function refreshRefundFlag(int|DiningSession|PickupAccount|DeliveryAccount $sessionId): void
    {
        $session = is_int($sessionId) ? DiningSession::query()->findOrFail($sessionId) : $sessionId;
        if ($session->settled_at) {
            $balance = $this->forAccount($session);
            CashPayment::query()->where(BillingAccounts::column(BillingAccounts::kind($session)), $session->id)->update(['has_pending_refund' => $balance['refund_due_cents'] > 0]);
        }
    }
}

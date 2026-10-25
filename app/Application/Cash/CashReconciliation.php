<?php

namespace App\Application\Cash;

use App\Enums\PaymentMethod;
use App\Models\CashShift;
use Illuminate\Support\Facades\DB;

class CashReconciliation
{
    /** @return array<string, array{received: int, refunded: int, expected: int, payments: int, refunds: int}> */
    public function totals(CashShift $shift): array
    {
        $payments = DB::table('cash_payments')->where('shift_id', $shift->id)->selectRaw('method, sum(amount_cents) as cents, count(*) as records')->groupBy('method')->get()->keyBy('method');
        $refunds = DB::table('cash_refunds')->where('shift_id', $shift->id)->selectRaw('method, sum(amount_cents) as cents, count(*) as records')->groupBy('method')->get()->keyBy('method');
        $totals = [];
        foreach (PaymentMethod::cases() as $method) {
            $received = (int) ($payments->get($method->value)->cents ?? 0);
            $refunded = (int) ($refunds->get($method->value)->cents ?? 0);
            $totals[$method->value] = ['received' => $received, 'refunded' => $refunded,
                'expected' => $received - $refunded + ($method === PaymentMethod::Cash ? $shift->opening_cents : 0),
                'payments' => (int) ($payments->get($method->value)->records ?? 0), 'refunds' => (int) ($refunds->get($method->value)->records ?? 0)];
        }

        return $totals;
    }
}

<?php

namespace App\Http\Presenters;

use App\Application\Cash\CashMoney;
use App\Application\Cash\CashReconciliation;
use App\Enums\PaymentMethod;
use App\Models\CashPayment;
use App\Models\CashShift;
use App\Models\User;

class CashViews
{
    /** @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public static function quote(array $snapshot): array
    {
        return ['total' => CashMoney::label($snapshot['total_cents']), 'gross' => CashMoney::label($snapshot['gross_cents']),
            'discount' => CashMoney::label($snapshot['discount_cents']),
            'additionalDiscount' => CashMoney::label($snapshot['discount_cents'] - $snapshot['original_discount_cents']),
            'discountReason' => $snapshot['policy']['reason'] ?? null,
            'lines' => array_map(fn ($line) => ['reference' => $line['reference'], 'name' => $line['name'], 'presentation' => $line['presentation'],
                'quantity' => $line['quantity'], 'options' => $line['options'], 'note' => $line['note'],
                'gross' => CashMoney::label($line['gross_cents']), 'discount' => CashMoney::label($line['discount_cents']), 'total' => CashMoney::label($line['total_cents'])], $snapshot['lines'])];
    }

    /** @return array<string, mixed> */
    public static function payment(CashPayment $payment): array
    {
        return ['id' => $payment->id, 'reference' => $payment->reference, 'amount' => CashMoney::label($payment->amount_cents),
            'method' => $payment->amount_cents === 0 ? 'Quitação sem valor' : PaymentMethod::from($payment->method)->label(),
            'received' => $payment->received_cents !== null ? CashMoney::label($payment->received_cents) : null,
            'change' => CashMoney::label($payment->change_cents), 'externalReference' => $payment->external_reference,
            'createdAt' => $payment->created_at->toIso8601String(), 'shiftId' => $payment->shift_id,
            'quote' => self::quote($payment->snapshot)];
    }

    /** @return array<string, mixed> */
    public static function shift(CashShift $shift): array
    {
        $rows = $shift->closing ?? app(CashReconciliation::class)->totals($shift);
        $ordered = [];
        foreach (PaymentMethod::cases() as $method) {
            $ordered[$method->value] = $rows[$method->value];
        }

        return ['id' => $shift->id, 'version' => $shift->version, 'openedAt' => $shift->opened_at->toIso8601String(),
            'closedAt' => $shift->closed_at?->toIso8601String(), 'opening' => CashMoney::label($shift->opening_cents), 'reason' => $shift->closing_reason,
            'openedBy' => User::query()->where('id', $shift->opened_by_id)->value('name'),
            'closedBy' => $shift->closed_by_id ? User::query()->where('id', $shift->closed_by_id)->value('name') : null,
            'rows' => collect($ordered)->map(fn ($row, $method) => ['method' => PaymentMethod::from($method)->label(), 'key' => $method,
                'received' => CashMoney::label($row['received']), 'refunded' => CashMoney::label($row['refunded']), 'expected' => CashMoney::label($row['expected']),
                'counted' => isset($row['counted']) ? CashMoney::label($row['counted']) : null,
                'difference' => isset($row['difference']) ? CashMoney::label($row['difference']) : null])->values()->all()];
    }
}

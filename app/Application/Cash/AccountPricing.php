<?php

namespace App\Application\Cash;

use App\Domain\Catalog\Money;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Illuminate\Validation\ValidationException;

class AccountPricing
{
    /**
     * @param  array{type: string, value: int, reason: string}|null  $policy
     * @param  list<array<string, mixed>>|null  $replacement
     * @return array<string, mixed>
     */
    public function quote(DiningSession|PickupAccount $session, ?array $policy = null, ?int $replaceOrder = null, ?array $replacement = null, bool $cancel = false): array
    {
        $lines = [];
        $orders = [];
        foreach ($session->orders()->with('items')->orderBy('id')->get() as $order) {
            $cancelled = $order->id === $replaceOrder ? $cancel : $order->status === 'cancelled';
            $orders[] = ['id' => $order->id, 'revision' => $order->content_revision, 'cancelled' => $cancelled];
            if ($cancelled) {
                continue;
            }
            $items = $order->id === $replaceOrder && $replacement !== null ? $replacement : array_values($order->items->map(fn ($item) => $item->snapshot)->all());
            foreach ($items as $position => $item) {
                $lines[] = ['order_id' => $order->id, 'reference' => $order->public_id, 'position' => $position, 'name' => $item['name'],
                    'presentation' => $item['variant_name'], 'quantity' => $item['quantity'], 'note' => $item['note'] ?? null,
                    'options' => array_map(fn ($option) => $option['quantity'].' × '.$option['name'], $item['options']),
                    'gross_cents' => $item['gross_cents'], 'original_discount_cents' => $item['discount_cents'], 'candidate_cents' => 0];
            }
        }
        $gross = 0;
        foreach ($lines as $line) {
            $gross = $this->add($gross, $line['gross_cents']);
        }
        if ($policy && $gross > 0) {
            if ($policy['type'] === 'percentage') {
                foreach ($lines as &$line) {
                    $this->checkProduct($line['gross_cents'], $policy['value'], 5000);
                    $line['candidate_cents'] = Money::discount($line['gross_cents'], 'percentage', $policy['value']);
                }
                unset($line);
            } else {
                $amount = min($gross, $policy['value']);
                $fractions = [];
                $allocated = 0;
                foreach ($lines as $i => &$line) {
                    $this->checkProduct($amount, $line['gross_cents']);
                    $product = $amount * $line['gross_cents'];
                    $line['candidate_cents'] = intdiv($product, $gross);
                    $allocated = $this->add($allocated, $line['candidate_cents']);
                    $fractions[] = ['i' => $i, 'remainder' => $product % $gross];
                }
                unset($line);
                usort($fractions, fn ($a, $b) => ($b['remainder'] <=> $a['remainder']) ?: ($a['i'] <=> $b['i']));
                $remaining = $amount - $allocated;
                if ($remaining < 0) {
                    $this->tooLarge();
                }
                $bonuses = array_flip(array_column(array_slice($fractions, 0, $remaining), 'i'));
                foreach ($lines as $i => &$line) {
                    if (isset($bonuses[$i])) {
                        $line['candidate_cents']++;
                    }
                }
                unset($line);
            }
        }
        foreach ($lines as &$line) {
            $line['discount_cents'] = max($line['original_discount_cents'], $line['candidate_cents']);
            $line['total_cents'] = $line['gross_cents'] - $line['discount_cents'];
            $line['total_label'] = Money::label($line['total_cents']);
            $line['discount_label'] = Money::label($line['discount_cents']);
        }
        unset($line);

        return ['session_id' => $session instanceof DiningSession ? $session->id : null, 'pickup_account_id' => $session instanceof PickupAccount ? $session->id : null, 'modality' => BillingAccounts::kind($session), 'locationLabel' => BillingAccounts::label($session), 'table_number' => $session instanceof DiningSession ? $session->table->number : null, 'orders' => $orders, 'lines' => $lines, 'policy' => $policy,
            'gross_cents' => $gross, 'original_discount_cents' => (int) array_sum(array_column($lines, 'original_discount_cents')),
            'discount_cents' => (int) array_sum(array_column($lines, 'discount_cents')), 'total_cents' => (int) array_sum(array_column($lines, 'total_cents'))];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function checkProduct(int $left, int $right, int $addition = 0): void
    {
        if ($right > 0 && $left > intdiv(PHP_INT_MAX - $addition, $right)) {
            $this->tooLarge();
        }
    }

    private function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $right > PHP_INT_MAX - $left) {
            $this->tooLarge();
        }

        return $left + $right;
    }

    private function tooLarge(): never
    {
        throw ValidationException::withMessages(['cash' => 'A conta excede o limite de cálculo exato. Revise os valores antes de registrar.']);
    }
}

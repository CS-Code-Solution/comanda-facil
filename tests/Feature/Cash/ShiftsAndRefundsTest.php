<?php

namespace Tests\Feature\Cash;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\CashPayments;
use App\Application\Cash\CashReconciliation;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Models\CashShift;
use App\Models\DiningOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class ShiftsAndRefundsTest extends CashTestCase
{
    private function cancel(DiningOrder $order, ?DiningContext $context = null): void
    {
        app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => $order->version,
            'status' => 'cancelled', 'reason' => 'Cliente solicitou cancelamento'], $context ?? DiningContext::staff($this->manager));
    }

    private function refundInput(string $amount = '53,00'): array
    {
        return ['operation_id' => (string) Str::uuid(), 'method' => 'cash', 'amount' => $amount, 'reason' => 'Devolução realizada pelo gestor', 'performed' => true];
    }

    private function closeShift(CashShift $shift, array $counted): CashShift
    {
        return app(CashShifts::class)->close($shift->id, ['operation_id' => (string) Str::uuid(), 'version' => $shift->fresh()->version,
            'counted' => $counted], DiningContext::staff($this->cashier));
    }

    public function test_only_cashier_or_manager_can_open_close_shift_and_one_open_shift_is_allowed(): void
    {
        $this->signIn($this->waiter)->post(route('cash.open'), ['operation_id' => (string) Str::uuid(), 'opening' => '100'])->assertForbidden();
        $this->expectException(ValidationException::class);
        app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100'], DiningContext::staff($this->manager));
    }

    public function test_open_operation_replay_survives_closure_without_opening_another_shift(): void
    {
        $this->closeShift($this->shift, ['cash' => '100', 'pix' => '0', 'card' => '0']);
        $context = DiningContext::staff($this->manager);
        $input = ['operation_id' => (string) Str::uuid(), 'opening' => '50'];
        $shift = app(CashShifts::class)->open($input, $context);
        $this->assertSame($shift->id, app(CashShifts::class)->open($input, $context)->id);
        $this->closeShift($shift, ['cash' => '50', 'pix' => '0', 'card' => '0']);
        $this->assertSame($shift->id, app(CashShifts::class)->open($input, $context)->id);
        $this->assertSame(0, CashShift::query()->whereNull('closed_at')->count());
    }

    public function test_expected_cash_includes_float_and_net_bill_not_tendered_cash(): void
    {
        $this->pay($this->order());
        $totals = app(CashReconciliation::class)->totals($this->shift);
        $this->assertSame(5300, $totals['cash']['received']);
        $this->assertSame(15300, $totals['cash']['expected']);
        $this->assertSame(0, $totals['pix']['expected']);
        $this->assertSame(0, $totals['card']['expected']);
    }

    public function test_difference_requires_reason_and_does_not_rewrite_receipts(): void
    {
        $payment = $this->pay($this->order());
        $data = ['operation_id' => (string) Str::uuid(), 'version' => 2, 'counted' => ['cash' => '152', 'pix' => '0', 'card' => '0']];
        $context = DiningContext::staff($this->cashier);
        try {
            app(CashShifts::class)->close($this->shift->id, $data, $context);
            $this->fail('Difference requires reason');
        } catch (ValidationException) {
            $this->assertNull($this->shift->fresh()->closed_at);
        }
        $closed = app(CashShifts::class)->close($this->shift->id, [...$data, 'reason' => 'Diferença de um real conferida'], $context);
        $this->assertSame(-100, $closed->closing['cash']['difference']);
        $this->assertSame(5300, $payment->fresh()->amount_cents);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_later_receipt_invalidates_stale_shift_count(): void
    {
        $oldVersion = $this->shift->version;
        $this->pay($this->order());
        $this->expectException(ValidationException::class);
        app(CashShifts::class)->close($this->shift->id, ['operation_id' => (string) Str::uuid(), 'version' => $oldVersion,
            'counted' => ['cash' => '153', 'pix' => '0', 'card' => '0']], DiningContext::staff($this->cashier));
    }

    public function test_close_is_idempotent_and_closed_shift_does_not_reopen(): void
    {
        $context = DiningContext::staff($this->cashier);
        $input = ['operation_id' => (string) Str::uuid(), 'version' => 1, 'counted' => ['cash' => '100', 'pix' => '0', 'card' => '0']];
        $service = app(CashShifts::class);
        $closed = $service->close($this->shift->id, $input, $context);
        $this->assertEquals($closed->closing, $service->close($this->shift->id, $input, $context)->closing);
        $this->assertSame(1, DB::table('cash_events')->where('action', 'shift_closed')->count());
        $this->expectException(ValidationException::class);
        $service->close($this->shift->id, [...$input, 'operation_id' => (string) Str::uuid()], $context);
    }

    public function test_pending_refund_blocks_table_closure_and_is_visible_in_cash_queue(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->cancel($order, DiningContext::staff($this->waiter));
        $this->signIn($this->cashier)->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page->has('pending', 1)->where('pending.0.reference', $payment->reference));
        $this->expectException(ValidationException::class);
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->cashier));
    }

    public function test_only_manager_can_record_actual_refund_with_reason_and_attestation(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->cancel($order);
        $input = $this->refundInput();
        $this->signIn($this->cashier)->post(route('cash.refund', $payment), $input)->assertForbidden();
        $this->signIn($this->manager)->post(route('cash.refund', $payment), [...$input, 'performed' => false])->assertSessionHasErrors('performed');
        $this->post(route('cash.refund', $payment), [...$input, 'reason' => ' '])->assertSessionHasErrors('reason');
        $id = app(CashPayments::class)->refund($payment->id, $input, DiningContext::staff($this->manager));
        $this->assertDatabaseHas('cash_refunds', ['id' => $id, 'amount_cents' => 5300, 'actor_id' => $this->manager->id]);
    }

    public function test_refund_preserves_original_payment_and_clears_pending_before_closure(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->cancel($order);
        app(CashPayments::class)->refund($payment->id, $this->refundInput(), DiningContext::staff($this->manager));
        $this->assertSame(5300, $payment->fresh()->amount_cents);
        $this->assertFalse($payment->fresh()->has_pending_refund);
        $this->assertSame(0, app(AccountBalance::class)->forSession($order->session->fresh())['refund_due_cents']);
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->cashier));
        $this->assertNotNull($order->session->fresh()->closed_at);
        $this->assertSame(10000, app(CashReconciliation::class)->totals($this->shift)['cash']['expected']);
    }

    public function test_partial_refunds_cannot_exceed_balance_and_replay_does_not_repeat_cash_outflow(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->cancel($order);
        $context = DiningContext::staff($this->manager);
        $input = $this->refundInput('20');
        $service = app(CashPayments::class);
        $first = $service->refund($payment->id, $input, $context);
        $this->assertSame($first, $service->refund($payment->id, $input, $context));
        $this->assertSame(3300, app(AccountBalance::class)->forSession($order->session->fresh())['refund_due_cents']);
        try {
            $service->refund($payment->id, $this->refundInput('34'), $context);
            $this->fail('Cannot refund above remaining balance');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cash_refunds', 1);
        }
        $service->refund($payment->id, $this->refundInput('33'), $context);
        $this->assertSame(5300, (int) DB::table('cash_refunds')->sum('amount_cents'));
    }

    public function test_refund_without_reduction_is_denied(): void
    {
        $payment = $this->pay($this->order());
        $this->expectException(ValidationException::class);
        app(CashPayments::class)->refund($payment->id, $this->refundInput(), DiningContext::staff($this->manager));
    }

    public function test_preparing_paid_order_only_manager_can_cancel(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'preparing'], DiningContext::staff($this->manager));
        $this->signIn($this->waiter)->post(route('operations.transition', $order), ['operation_id' => (string) Str::uuid(), 'version' => 2, 'status' => 'cancelled', 'reason' => 'Tentativa sem gestor'])->assertForbidden();
        $this->cancel($order->fresh());
        $this->assertTrue($payment->fresh()->has_pending_refund);
    }

    public function test_refund_in_later_shift_keeps_original_closed_shift_unchanged_and_table_closed(): void
    {
        $order = $this->order();
        $payment = $this->pay($order, 'pix');
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->cashier));
        $closed = $this->closeShift($this->shift, ['cash' => '100', 'pix' => '53', 'card' => '0']);
        $snapshot = $closed->closing;
        $next = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100'], DiningContext::staff($this->cashier));
        $this->cancel($order);
        $id = app(CashPayments::class)->refund($payment->id, [...$this->refundInput(), 'method' => 'pix'], DiningContext::staff($this->manager));
        $this->assertDatabaseHas('cash_refunds', ['id' => $id, 'shift_id' => $next->id]);
        $this->assertEquals($snapshot, $closed->fresh()->closing);
        $this->assertNotNull($order->session->fresh()->closed_at);
        $this->assertSame(1, $this->table->fresh()->cycle);
        $this->assertSame(-5300, app(CashReconciliation::class)->totals($next)['pix']['expected']);
        $final = $this->closeShift($next, ['cash' => '100', 'pix' => '-53', 'card' => '0']);
        $this->assertSame(0, $final->closing['pix']['difference']);
    }

    public function test_cashier_cannot_cancel_paid_order_in_closed_session(): void
    {
        $order = $this->order();
        $this->pay($order);
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->cashier));
        $this->expectException(AuthorizationException::class);
        $this->cancel($order, DiningContext::staff($this->cashier));
    }

    public function test_paid_reduction_recalculates_frozen_account_discount_and_refund_value(): void
    {
        $order = $this->order(['items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]]);
        $payment = $this->pay($order, 'pix', $this->manager, ['discount' => ['type' => 'percentage', 'value' => '10', 'reason' => 'Desconto de fechamento']]);
        $this->assertSame(9540, $payment->amount_cents);
        $context = DiningContext::staff($this->waiter);
        $id = app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Reduzir para uma pizza',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $context);
        app(OrderFulfillment::class)->confirm($order->id, $id, $context);
        $this->assertSame(4770, app(AccountBalance::class)->forSession($order->session->fresh())['refund_due_cents']);
        $this->assertSame(9540, $payment->fresh()->amount_cents);
    }

    public function test_legacy_settlement_is_not_converted_into_fake_receipt(): void
    {
        $order = $this->order();
        $order->session->forceFill(['settled_at' => now()])->save();
        try {
            $this->cancel($order);
            $this->fail('Legacy settlement requires reconciliation');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cash_payments', 0);
            $this->assertDatabaseCount('cash_refunds', 0);
        }
    }

    public function test_order_without_open_cash_shift_stays_unassigned_instead_of_using_future_payment_shift(): void
    {
        $this->closeShift($this->shift, ['cash' => '100', 'pix' => '0', 'card' => '0']);
        $order = $this->order();
        $this->assertNull($order->cash_shift_id);
        app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100'], DiningContext::staff($this->cashier));
        $this->pay($order, 'pix');
        $this->assertNull($order->fresh()->cash_shift_id);
    }

    public function test_refund_operation_cannot_be_reused_for_another_amount(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->cancel($order);
        $input = $this->refundInput('20');
        app(CashPayments::class)->refund($payment->id, $input, DiningContext::staff($this->manager));
        $this->expectException(ValidationException::class);
        app(CashPayments::class)->refund($payment->id, [...$input, 'amount' => '21'], DiningContext::staff($this->manager));
    }
}

<?php

namespace Tests\Feature\Cash;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class AccountPaymentsTest extends CashTestCase
{
    public function test_cash_payment_is_full_amount_and_change_is_server_calculated(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->assertSame(5300, $payment->amount_cents);
        $this->assertSame(10000, $payment->received_cents);
        $this->assertSame(4700, $payment->change_cents);
        $this->assertNotNull($order->session->fresh()->settled_at);
        $this->assertSame($this->shift->id, $order->cash_shift_id);
        $this->assertDatabaseHas('cash_events', ['action' => 'payment_recorded', 'actor_id' => $this->cashier->id]);
    }

    public function test_pix_and_card_are_manual_records_without_cash_change(): void
    {
        $order = $this->order();
        $payment = $this->pay($order, 'pix');
        $this->assertSame('pix', $payment->method);
        $this->assertNull($payment->received_cents);
        $this->assertSame(0, $payment->change_cents);
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->cashier));
        $another = $this->order();
        $this->assertSame('card', $this->pay($another, 'card')->method);
    }

    public function test_same_confirmation_replays_one_payment_and_changed_method_is_rejected(): void
    {
        $order = $this->order();
        $service = app(CashPayments::class);
        $context = DiningContext::staff($this->cashier);
        $intent = $service->preview($order->session_id, [], $context);
        $input = ['method' => 'cash', 'received' => '100,00'];
        $payment = $service->confirm($order->session_id, $intent, $input, $context);
        $this->assertSame($payment->id, $service->confirm($order->session_id, $intent, $input, $context)->id);
        $this->assertDatabaseCount('cash_payments', 1);
        $this->expectException(ValidationException::class);
        $service->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
    }

    public function test_two_previews_do_not_create_two_payments_for_one_account(): void
    {
        $order = $this->order();
        $service = app(CashPayments::class);
        $context = DiningContext::staff($this->cashier);
        $first = $service->preview($order->session_id, [], $context);
        $second = $service->preview($order->session_id, [], $context);
        $service->confirm($order->session_id, $first, ['method' => 'pix'], $context);
        try {
            $service->confirm($order->session_id, $second, ['method' => 'card'], $context);
            $this->fail('Cannot pay twice');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cash_payments', 1);
        }
    }

    public function test_new_order_invalidates_payment_preview_without_partial_receipt(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        $this->order();
        try {
            app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
            $this->fail('Changed account requires new preview');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cash_payments', 0);
            $this->assertNull($order->session->fresh()->settled_at);
        }
    }

    public function test_advancing_preparation_does_not_invalidate_unchanged_financial_preview(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        app(OrderFulfillment::class)->transition($order->id, ['version' => 1, 'operation_id' => (string) Str::uuid(), 'status' => 'preparing'], DiningContext::staff($this->manager));
        $payment = app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
        $this->assertSame(5300, $payment->amount_cents);
    }

    public function test_payment_uses_confirmed_sale_snapshot_after_catalog_price_change(): void
    {
        $order = $this->order();
        $this->variant->forceFill(['price_cents' => 9900])->save();
        $this->assertSame(5300, $this->pay($order)->amount_cents);
    }

    public function test_expired_preview_and_another_actor_are_denied(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        $this->signIn($this->cashier)->get(route('cash.review', ['session' => $order->session_id, 'intent' => $intent]))->assertInertia(fn (Assert $page) => $page
            ->where('expiresAt', fn (string $date): bool => str_contains($date, 'T') && str_ends_with($date, '+00:00')));
        $this->signIn($this->manager)->post(route('cash.confirm', ['session' => $order->session_id, 'intent' => $intent]), ['method' => 'pix'])->assertNotFound();
        $this->travel(11)->minutes();
        $this->expectException(ValidationException::class);
        app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
    }

    public function test_new_shift_requires_new_payment_preview(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        app(CashShifts::class)->close($this->shift->id, ['operation_id' => (string) Str::uuid(), 'version' => 1,
            'counted' => ['cash' => '100', 'pix' => '0', 'card' => '0']], $context);
        app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100'], $context);
        $this->expectException(ValidationException::class);
        app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
    }

    public function test_payment_requires_open_shift(): void
    {
        $order = $this->order();
        app(CashShifts::class)->close($this->shift->id, ['operation_id' => (string) Str::uuid(), 'version' => 1,
            'counted' => ['cash' => '100', 'pix' => '0', 'card' => '0']], DiningContext::staff($this->cashier));
        $this->expectException(ValidationException::class);
        $this->pay($order);
    }

    public function test_cash_must_cover_entire_bill_and_browser_total_is_not_authority(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        $this->signIn($this->cashier)->post(route('cash.confirm', ['session' => $order->session_id, 'intent' => $intent]), ['method' => 'cash', 'received' => '1,00', 'amount' => '1,00'])->assertSessionHasErrors('received');
        $payment = app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix', 'amount' => '1,00'], $context);
        $this->assertSame(5300, $payment->amount_cents);
    }

    public function test_cashier_waiter_and_guest_cannot_grant_account_discount(): void
    {
        $order = $this->order();
        $this->signIn($this->waiter)->get(route('cash.account', $order->session_id))->assertForbidden();
        $this->signIn($this->cashier)->post(route('cash.preview', $order->session_id), ['discount' => ['type' => 'percentage', 'value' => '10', 'reason' => 'Desconto sem permissão']])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(CashPayments::class)->preview($order->session_id, [], DiningContext::guest($this->grant()));
    }

    public function test_best_discount_per_item_does_not_stack_with_existing_discount(): void
    {
        $order = $this->order(['items' => [['variant_id' => $this->variant->id, 'quantity' => 1,
            'manual_discount' => ['type' => 'percentage', 'value' => '20']], ['variant_id' => $this->variant->id, 'quantity' => 1]]]);
        $payment = $this->pay($order, 'pix', $this->manager, ['discount' => ['type' => 'percentage', 'value' => '10', 'reason' => 'Cortesia de fechamento']]);
        $this->assertSame(9010, $payment->amount_cents);
        $this->assertSame(1590, $payment->snapshot['discount_cents']);
        $this->assertSame(9540, $order->fresh()->total_cents);
    }

    public function test_fixed_discount_distributes_residual_cent_deterministically(): void
    {
        $this->variant->forceFill(['price_cents' => 1])->save();
        $order = $this->order(['items' => array_fill(0, 3, ['variant_id' => $this->variant->id, 'quantity' => 1])]);
        $payment = $this->pay($order, 'pix', $this->manager, ['discount' => ['type' => 'fixed', 'value' => '0,01', 'reason' => 'Um centavo de cortesia']]);
        $this->assertSame(2, $payment->amount_cents);
        $this->assertSame([1, 0, 0], array_column($payment->snapshot['lines'], 'discount_cents'));
    }

    public function test_zero_bill_is_recorded_without_bank_receipt_or_cash_movement(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->manager);
        $intent = app(CashPayments::class)->preview($order->session_id, ['discount' => ['type' => 'percentage', 'value' => '100', 'reason' => 'Cortesia integral autorizada']], $context);
        $payment = app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'cash', 'received' => '0'], $context);
        $this->assertSame(0, $payment->amount_cents);
        $this->assertSame(0, $payment->change_cents);
        app(ManageDining::class)->close($this->table->id, $order->session_id, $context);
        $this->assertNotNull($order->session->fresh()->closed_at);
    }

    public function test_discount_reason_and_bounds_are_required(): void
    {
        $order = $this->order();
        $this->signIn($this->manager)->post(route('cash.preview', $order->session_id), ['discount' => ['type' => 'percentage', 'value' => '10']])->assertSessionHasErrors('discount.reason');
        $this->post(route('cash.preview', $order->session_id), ['discount' => ['type' => 'percentage', 'value' => '101', 'reason' => 'Percentual inválido']])->assertSessionHasErrors('discount.value');
    }

    public function test_paid_increase_is_blocked_and_reduction_creates_refund_due_without_fake_outflow(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $context = DiningContext::staff($this->waiter);
        try {
            app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Tentativa de aumento', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]], $context);
            $this->fail('Paid increase must fail');
        } catch (ValidationException) {
            $this->assertSame(5300, $order->fresh()->total_cents);
        }
        app(OrderFulfillment::class)->transition($order->id, ['version' => 1, 'operation_id' => (string) Str::uuid(), 'status' => 'cancelled', 'reason' => 'Cliente desistiu antes do preparo'], $context);
        $this->assertSame(5300, app(AccountBalance::class)->forSession($order->session->fresh())['refund_due_cents']);
        $this->assertTrue($payment->fresh()->has_pending_refund);
        $this->assertDatabaseCount('cash_refunds', 0);
    }

    public function test_revoked_context_cannot_record_payment(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $intent = app(CashPayments::class)->preview($order->session_id, [], $context);
        $this->cashier->forceFill(['auth_version' => $this->cashier->auth_version + 1])->save();
        $this->expectException(AuthorizationException::class);
        app(CashPayments::class)->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
    }

    public function test_rollback_does_not_leave_payment_settlement_or_financial_audit(): void
    {
        $order = $this->order();
        $events = DB::table('cash_events')->count();
        DB::beginTransaction();
        $this->pay($order);
        DB::rollBack();
        $this->assertDatabaseCount('cash_payments', 0);
        $this->assertDatabaseCount('cash_events', $events);
        $this->assertNull($order->session->fresh()->settled_at);
    }

    public function test_account_and_receipt_are_private_and_display_original_record(): void
    {
        $order = $this->order();
        $payment = $this->pay($order);
        $this->signIn($this->cashier)->get(route('cash.receipt', $payment))->assertInertia(fn (Assert $page) => $page->where('payment.amount', 'R$ 53,00')->where('payment.change', 'R$ 47,00'));
        $this->signIn(User::factory()->create(['role' => 'cozinha']))->get(route('cash.receipt', $payment))->assertForbidden();
    }

    public function test_extreme_values_are_rejected_without_floating_point_or_partial_record(): void
    {
        $order = $this->order();
        $item = $order->items()->sole();
        $snapshot = $item->snapshot;
        $snapshot['gross_cents'] = 100000000000;
        $snapshot['discount_cents'] = 99999994700;
        $item->forceFill(['snapshot' => $snapshot])->save();
        try {
            app(CashPayments::class)->preview($order->session_id, ['discount' => ['type' => 'fixed', 'value' => '1000000', 'reason' => 'Teste de limite exato']], DiningContext::staff($this->manager));
            $this->fail('Extreme arithmetic must be rejected explicitly');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cash_payments', 0);
            $this->assertNull($order->session->fresh()->settled_at);
        }
    }

    public function test_financial_realtime_channel_is_scoped_to_cashier_or_manager(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        Broadcast::purge();
        require base_path('routes/channels.php');
        $input = ['channel_name' => 'private-operations.cashier', 'socket_id' => '123.456'];
        $this->signIn($this->waiter)->postJson('/broadcasting/auth', $input)->assertForbidden();
        $this->signIn($this->cashier)->postJson('/broadcasting/auth', $input)->assertOk()->assertJsonStructure(['auth']);
    }
}

<?php

namespace Tests\Feature\Cash;

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderFulfillment;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use App\Models\CashPayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CashTestCase;

class WaiterPaymentsTest extends CashTestCase
{
    public function test_waiter_reaches_account_from_table_without_entering_cash_administration(): void
    {
        $order = $this->order();
        $url = route('cash.account', $order->session_id);
        $this->signIn($this->waiter)->get(route('dining.show', $this->table))->assertInertia(fn (Assert $p) => $p->where('session.cashUrl', $url));
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('canPay', true)->where('canDiscount', false)->where('canRefund', false)
            ->where('backUrl', route('dining.show', $this->table))->where('backLabel', 'Voltar à mesa')->where('realtimeChannel', 'operations.dining'));
        $this->get(route('cash.index'))->assertForbidden();
        $this->get(route('cash.shift', $this->shift))->assertForbidden();
        $this->post(route('cash.open'), ['operation_id' => (string) Str::uuid(), 'opening' => '100'])->assertForbidden();
        $this->post(route('cash.close', $this->shift), [])->assertForbidden();
    }

    public static function methods(): array
    {
        return [['cash'], ['pix'], ['card']];
    }

    #[DataProvider('methods')]
    public function test_waiter_records_integral_payment_then_closes_attendance(string $method): void
    {
        $order = $this->order();
        $this->signIn($this->waiter)->post(route('cash.preview', $order->session_id))->assertRedirect();
        $intent = DB::table('cash_payment_intents')->where('actor_id', $this->waiter->id)->sole()->id;
        $input = ['method' => $method, 'received' => $method === 'cash' ? '100,00' : null];
        $url = route('cash.confirm', ['session' => $order->session_id, 'intent' => $intent]);
        $this->post($url, $input)->assertRedirect(route('cash.account', $order->session_id));
        $this->post($url, $input)->assertRedirect();
        $payment = CashPayment::sole();
        $this->assertSame($this->waiter->id, $payment->actor_id);
        $this->assertSame(5300, $payment->amount_cents);
        $this->assertSame($method === 'cash' ? 4700 : 0, $payment->change_cents);
        $this->assertSame($this->shift->id, $payment->shift_id);
        $this->assertNotNull($order->session->fresh()->settled_at);
        $this->assertDatabaseHas('cash_events', ['action' => 'payment_recorded', 'actor_id' => $this->waiter->id]);
        $this->get(route('cash.receipt', $payment))->assertOk();
        $this->post(route('dining.close', ['table' => $this->table->id, 'sessionId' => $order->session_id]))->assertRedirect();
        $this->assertNotNull($order->session->fresh()->closed_at);
        $this->assertSame($this->waiter->id, $order->session->fresh()->closed_by_id);
        $this->assertSame(1, $this->table->fresh()->cycle);
        $this->assertDatabaseCount('cash_payments', 1);
    }

    public function test_waiter_cannot_close_unpaid_attendance_or_record_insufficient_cash(): void
    {
        $order = $this->order();
        $this->signIn($this->waiter)->post(route('dining.close', ['table' => $this->table->id, 'sessionId' => $order->session_id]))->assertSessionHasErrors();
        $this->post(route('cash.preview', $order->session_id))->assertRedirect();
        $intent = DB::table('cash_payment_intents')->sole()->id;
        $this->post(route('cash.confirm', ['session' => $order->session_id, 'intent' => $intent]), ['method' => 'cash', 'received' => '1,00'])->assertSessionHasErrors('received');
        $this->assertNull($order->session->fresh()->settled_at);
        $this->assertNull($order->session->fresh()->closed_at);
        $this->assertDatabaseCount('cash_payments', 0);
    }

    public function test_waiter_requires_open_shift_and_cannot_grant_discount(): void
    {
        $order = $this->order();
        $this->signIn($this->waiter)->post(route('cash.preview', $order->session_id), ['discount' => ['type' => 'percentage', 'value' => '10', 'reason' => 'Desconto sem permissão']])->assertForbidden();
        app(CashShifts::class)->close($this->shift->id, ['operation_id' => (string) Str::uuid(), 'version' => 1,
            'counted' => ['cash' => '100', 'pix' => '0', 'card' => '0']], DiningContext::staff($this->cashier));
        $this->post(route('cash.preview', $order->session_id))->assertSessionHasErrors('cash');
        $this->assertDatabaseCount('cash_payments', 0);
    }

    public function test_pending_refund_prevents_waiter_closing_and_only_manager_can_record_refund(): void
    {
        $order = $this->order();
        $payment = $this->pay($order, 'pix', $this->waiter);
        app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1,
            'status' => 'cancelled', 'reason' => 'Cliente desistiu antes do preparo'], DiningContext::staff($this->waiter));
        $close = route('dining.close', ['table' => $this->table->id, 'sessionId' => $order->session_id]);
        $this->signIn($this->waiter)->post($close)->assertSessionHasErrors();
        $refund = ['operation_id' => (string) Str::uuid(), 'method' => 'pix', 'amount' => '53,00', 'reason' => 'Devolução efetivamente realizada', 'performed' => true];
        $this->post(route('cash.refund', $payment), $refund)->assertForbidden();
        $this->assertNull($order->session->fresh()->closed_at);
        $this->signIn($this->manager)->post(route('cash.refund', $payment), $refund)->assertRedirect();
        $this->signIn($this->waiter)->post($close)->assertRedirect();
        $this->assertNotNull($order->session->fresh()->closed_at);
    }

    public function test_another_waiter_cannot_confirm_the_first_waiters_preview(): void
    {
        $order = $this->order();
        $intent = app(CashPayments::class)->preview($order->session_id, [], DiningContext::staff($this->waiter));
        $this->signIn(User::factory()->create(['role' => 'garcom']))->post(route('cash.confirm', ['session' => $order->session_id, 'intent' => $intent]), ['method' => 'pix'])->assertNotFound();
        $this->assertDatabaseCount('cash_payments', 0);
    }

    public function test_receiving_permission_does_not_grant_pickup_payment_or_its_receipt(): void
    {
        $context = PickupContext::staff($this->manager);
        $service = app(PickupOrders::class);
        $service->service(true, 'Abrir retirada de fixture', $context);
        $intent = $service->preview(['phone' => '86999001122', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $context);
        $order = $service->confirm($intent->id, $context);
        $payments = app(CashPayments::class);
        $actor = DiningContext::staff($this->cashier);
        $intentId = $payments->preview($order->pickup_account_id, [], $actor, 'pickup');
        $payment = $payments->confirm($order->pickup_account_id, $intentId, ['method' => 'pix'], $actor, 'pickup');
        $this->signIn($this->waiter)->get(route('cash.pickup.account', ['session' => $order->pickup_account_id]))->assertForbidden();
        $this->get(route('cash.receipt', $payment))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $payments->preview($order->pickup_account_id, [], DiningContext::staff($this->waiter), 'pickup');
    }
}

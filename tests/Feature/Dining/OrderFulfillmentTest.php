<?php

namespace Tests\Feature\Dining;

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiningTestCase;

class OrderFulfillmentTest extends DiningTestCase
{
    private function order(): DiningOrder
    {
        $intent = $this->preview();

        return app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
    }

    private function move(DiningOrder $order, string $status, ?User $actor = null, array $extra = []): DiningOrder
    {
        return app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => $order->version,
            'status' => $status, 'reason' => 'Motivo registrado no teste', ...$extra], DiningContext::staff($actor ?? $this->manager));
    }

    private function change(DiningOrder $order, int $quantity = 2, ?User $actor = null): string
    {
        return app(OrderFulfillment::class)->preview($order->id, ['version' => $order->version, 'reason' => 'Cliente corrigiu quantidade',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => $quantity, 'note' => 'Nova observação']]], DiningContext::staff($actor ?? $this->waiter));
    }

    public function test_order_confirmation_atomically_creates_two_tickets_history_and_one_invalidation(): void
    {
        $order = $this->order();
        app(DiningOrders::class)->confirm($order->intent_id, $this->table->id, DiningContext::staff($this->waiter));
        $this->assertDatabaseCount('dining_orders', 1);
        $this->assertDatabaseCount('print_jobs', 2);
        $this->assertDatabaseCount('order_history', 1);
        $this->assertDatabaseCount('operations_outbox', 1);
        $kitchen = DB::table('print_jobs')->where('target', 'kitchen')->value('snapshot');
        $this->assertStringNotContainsString('cents', $kitchen);
        $this->assertStringNotContainsString('discount', $kitchen);
        $this->assertStringContainsString('Calabresa', $kitchen);
    }

    public function test_kitchen_prepares_and_readies_then_waiter_serves_without_skipping(): void
    {
        $kitchen = User::factory()->create(['role' => 'cozinha']);
        $order = $this->order();
        $this->move($order, 'preparing', $kitchen);
        $this->move($order->fresh(), 'ready', $kitchen);
        $this->move($order->fresh(), 'served', $this->waiter);
        $this->assertSame('served', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->started_at);
        $this->assertNotNull($order->fresh()->ready_at);
        $this->assertNotNull($order->fresh()->served_at);
        $this->assertDatabaseCount('order_history', 4);
        $this->assertDatabaseCount('print_jobs', 2);
    }

    public function test_state_skip_is_rejected_without_side_effects(): void
    {
        $order = $this->order();
        try {
            $this->move($order, 'ready');
            $this->fail('Must reject skipping preparation');
        } catch (ValidationException) {
            $this->assertSame('confirmed', $order->fresh()->status);
            $this->assertDatabaseCount('order_history', 1);
            $this->assertDatabaseCount('operations_outbox', 1);
        }
    }

    public function test_waiter_cannot_start_preparation(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->move($this->order(), 'preparing', $this->waiter);
    }

    public function test_kitchen_cannot_mark_served_or_cancel(): void
    {
        $order = $this->order();
        $this->move($order, 'preparing');
        $this->move($order->fresh(), 'ready');
        $this->expectException(AuthorizationException::class);
        $this->move($order->fresh(), 'served', User::factory()->create(['role' => 'cozinha']));
    }

    public function test_same_operation_is_idempotent_but_changed_command_cannot_reuse_it(): void
    {
        $order = $this->order();
        $uuid = (string) Str::uuid();
        $this->move($order, 'preparing', extra: ['operation_id' => $uuid]);
        $this->move($order, 'preparing', extra: ['operation_id' => $uuid]);
        $this->assertDatabaseCount('order_history', 2);
        $this->expectException(ValidationException::class);
        $this->move($order->fresh(), 'ready', extra: ['operation_id' => $uuid]);
    }

    public function test_stale_version_does_not_overwrite_another_device(): void
    {
        $order = $this->order();
        $this->move($order, 'preparing');
        $this->expectException(ValidationException::class);
        $this->move($order, 'cancelled');
    }

    public function test_amendment_requires_preview_and_keeps_before_after_and_replacement_tickets(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        $this->assertSame(5300, $order->fresh()->total_cents);
        $context = DiningContext::staff($this->waiter);
        app(OrderFulfillment::class)->confirm($order->id, $intent, $context);
        app(OrderFulfillment::class)->confirm($order->id, $intent, $context);
        $this->assertSame(10600, $order->fresh()->total_cents);
        $this->assertSame(2, $order->fresh()->version);
        $this->assertSame(2, $order->fresh()->content_revision);
        $history = DB::table('order_history')->where('action', 'amended')->sole();
        $this->assertSame(5300, json_decode($history->before, true)['total_cents']);
        $this->assertSame(10600, json_decode($history->after, true)['total_cents']);
        $this->assertSame($this->waiter->id, $history->actor_id);
        $this->assertDatabaseCount('print_jobs', 4);
        $this->assertSame(2, DB::table('print_jobs')->where('status', 'superseded')->count());
        $this->assertSame(2, DB::table('print_jobs')->where('kind', 'amendment')->count());
    }

    public function test_cashier_can_amend_but_cannot_apply_manager_discount(): void
    {
        $cashier = User::factory()->create(['role' => 'caixa']);
        $order = $this->order();
        $intent = $this->change($order, 2, $cashier);
        app(OrderFulfillment::class)->confirm($order->id, $intent, DiningContext::staff($cashier));
        $this->assertSame(10600, $order->fresh()->total_cents);
        $this->expectException(AuthorizationException::class);
        app(OrderFulfillment::class)->preview($order->id, ['version' => 2, 'reason' => 'Tentativa de desconto', 'items' => [
            ['variant_id' => $this->variant->id, 'quantity' => 1, 'manual_discount' => ['type' => 'percentage', 'value' => '10']]]], DiningContext::staff($cashier));
    }

    public function test_preparation_invalidates_preexisting_edit_preview(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        $this->move($order, 'preparing');
        try {
            app(OrderFulfillment::class)->confirm($order->id, $intent, DiningContext::staff($this->waiter));
            $this->fail('Cannot edit a preparing order');
        } catch (ValidationException) {
            $this->assertSame(5300, $order->fresh()->total_cents);
            $this->assertDatabaseCount('print_jobs', 2);
        }
    }

    public function test_edit_rechecks_price_and_requires_new_preview_when_changed(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        $this->variant->forceFill(['price_cents' => 5400])->save();
        $this->expectException(ValidationException::class);
        app(OrderFulfillment::class)->confirm($order->id, $intent, DiningContext::staff($this->waiter));
    }

    public function test_expired_edit_preview_is_rejected(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        $this->travel(11)->minutes();
        $this->expectException(ValidationException::class);
        app(OrderFulfillment::class)->confirm($order->id, $intent, DiningContext::staff($this->waiter));
    }

    public function test_edit_requires_same_actor_and_session_version(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        $this->signIn($this->manager)->post(route('operations.confirm', ['order' => $order->id, 'intent' => $intent]))->assertNotFound();
        $this->assertSame(5300, $order->fresh()->total_cents);
    }

    public function test_cancellation_before_preparation_excludes_balance_and_preserves_history(): void
    {
        $order = $this->order();
        $this->move($order, 'cancelled', $this->waiter);
        $this->assertSame(5300, $order->fresh()->total_cents);
        $this->signIn($this->waiter)->get(route('dining.show', $this->table))->assertInertia(fn (Assert $page) => $page->where('session.total', 'R$ 0,00')->where('orders.0.status', 'Cancelado'));
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->waiter));
        $this->assertNotNull($order->session->fresh()->closed_at);
        $this->assertSame(2, DB::table('print_jobs')->where('kind', 'cancellation')->count());
    }

    public function test_after_preparation_only_manager_can_cancel_with_reason(): void
    {
        $order = $this->order();
        $this->move($order, 'preparing');
        try {
            $this->move($order->fresh(), 'cancelled', $this->waiter);
            $this->fail('Must require manager');
        } catch (AuthorizationException) {
            $this->assertSame('preparing', $order->fresh()->status);
        }
        $this->move($order->fresh(), 'cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_correction_and_cancellation_require_nonempty_reason(): void
    {
        $order = $this->order();
        $this->signIn($this->waiter)->post(route('operations.transition', $order), ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => ' '])->assertSessionHasErrors('reason');
        $this->post(route('operations.preview', $order), ['version' => 1, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]])->assertSessionHasErrors('reason');
    }

    public function test_paid_order_cannot_increase_or_be_cancelled_without_refund(): void
    {
        $order = $this->order();
        $order->session->forceFill(['settled_at' => now()])->save();
        try {
            $this->change($order);
            $this->fail('Cannot increase paid order');
        } catch (ValidationException) {
            $this->assertSame(5300, $order->fresh()->total_cents);
        }
        $this->expectException(ValidationException::class);
        $this->move($order, 'cancelled');
    }

    public function test_revoked_staff_context_cannot_mutate_and_guest_cannot_edit(): void
    {
        $order = $this->order();
        $context = DiningContext::staff($this->waiter);
        $this->waiter->forceFill(['auth_version' => $this->waiter->auth_version + 1])->save();
        $this->expectException(AuthorizationException::class);
        app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Correção antiga', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]], $context);
    }

    public function test_pausing_new_orders_does_not_block_fulfillment(): void
    {
        $order = $this->order();
        app(ManageDining::class)->service(false, 'Pausar novos pedidos', DiningContext::staff($this->manager));
        $this->move($order, 'preparing');
        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_edit_roundtrip_preserves_manual_discount_decimal_and_note(): void
    {
        $context = DiningContext::staff($this->manager);
        $intent = $this->preview($context, ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1,
            'manual_discount' => ['type' => 'percentage', 'value' => '10,00']]]]);
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, $context);
        $response = $this->signIn($this->manager)->get(route('operations.edit', $order));
        $response->assertInertia(fn (Assert $page) => $page->where('initialItems.0.input.manual_discount.value', '10,00'));
        $items = $response->inertiaProps('initialItems');
        $input = $items[0]['input'];
        $input['options'] = (array) $input['options'];
        $input['note'] = 'Observação corrigida';
        $change = app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Ajustar observação', 'items' => [$input]], $context);
        app(OrderFulfillment::class)->confirm($order->id, $change, $context);
        $this->assertSame(4770, $order->fresh()->total_cents);
        $this->assertSame('Observação corrigida', $order->items()->sole()->note);
    }

    public function test_guest_context_cannot_amend_an_order(): void
    {
        $order = $this->order();
        $this->expectException(AuthorizationException::class);
        app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Cliente tentando editar',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]], DiningContext::guest($this->grant()));
    }

    public function test_financial_session_closure_does_not_erase_or_block_preparation(): void
    {
        $context = DiningContext::staff($this->manager);
        $intent = $this->preview($context, ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1,
            'manual_discount' => ['type' => 'percentage', 'value' => '100,00']]]]);
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, $context);
        app(ManageDining::class)->close($this->table->id, $order->session_id, $context);
        $this->move($order, 'preparing');
        $this->move($order->fresh(), 'ready');
        $this->move($order->fresh(), 'served');
        $this->assertSame('served', $order->fresh()->status);
        $this->assertNotNull($order->session->fresh()->closed_at);
    }

    public function test_paid_reduction_requires_refund_and_cancelled_order_is_terminal(): void
    {
        $order = $this->order();
        $intent = $this->change($order);
        app(OrderFulfillment::class)->confirm($order->id, $intent, DiningContext::staff($this->waiter));
        $order->session->forceFill(['settled_at' => now()])->save();
        try {
            $this->change($order->fresh(), 1, $this->manager);
            $this->fail('Refund is required');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('estorno', $error->getMessage());
            $this->assertSame(10600, $order->fresh()->total_cents);
        }
        $order->session->forceFill(['settled_at' => null])->save();
        $this->move($order->fresh(), 'cancelled');
        $this->expectException(ValidationException::class);
        $this->move($order->fresh(), 'preparing');
    }

    public function test_kitchen_payload_contains_preparation_items_but_no_commercial_history(): void
    {
        $order = $this->order();
        $this->signIn(User::factory()->create(['role' => 'cozinha']))->get(route('operations.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('order.total', null)->missing('order.items.0.total')->has('history', 0)->where('order.items.0.name', 'Calabresa'));
    }
}

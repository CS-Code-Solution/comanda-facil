<?php

namespace Tests\Feature\Pickup;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\BillingAccounts;
use App\Application\Cash\CashPayments;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OperationsChanges;
use App\Application\Orders\OrderFulfillment;
use App\Application\Orders\OrderPrinting;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupGrant;
use App\Application\Pickup\PickupOrders;
use App\Models\CashPayment;
use App\Models\DiningOrder;
use App\Models\PickupAccount;
use App\Models\PickupIntent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class PickupOrdersTest extends CashTestCase
{
    public function test_pickup_print_failure_reprint_and_new_revision_share_existing_controls(): void
    {
        $order = $this->pickup();
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->cashier);
        $job = DB::table('print_jobs')->where('target', 'cashier')->sole();
        $printing->act($job->id, 'claim', [], $context);
        $printing->act($job->id, 'failed', ['reason' => 'Papel ausente na fixture'], $context);
        $request = ['reason' => 'Papel reposto na fixture', 'operation_id' => (string) Str::uuid()];
        $copy = $printing->act($job->id, 'reprint', $request, $context);
        $this->assertSame($copy, $printing->act($job->id, 'reprint', $request, $context));
        $this->assertDatabaseHas('print_jobs', ['id' => $copy, 'copy' => 2, 'order_id' => $order->id]);
        $this->movePickup($order, 'cancelled', $this->cashier);
        $this->assertDatabaseHas('print_jobs', ['id' => $copy, 'status' => 'superseded']);
        $this->assertDatabaseCount('dining_orders', 1);
    }

    public function test_manager_late_cancellation_preserves_collection_and_original_payment(): void
    {
        $order = $this->pickup();
        $payment = $this->payPickup($order);
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        $this->movePickup($order, 'collected', $this->cashier);
        $collected = $order->fresh()->collected_at;
        $closed = PickupAccount::findOrFail($order->pickup_account_id)->closed_at;
        $this->movePickup($order, 'cancelled');
        $this->assertSame($collected->timestamp, $order->fresh()->collected_at->timestamp);
        $this->assertSame($closed->timestamp, PickupAccount::findOrFail($order->pickup_account_id)->closed_at->timestamp);
        $this->assertTrue($payment->fresh()->has_pending_refund);
        $this->assertSame(5300, $payment->fresh()->amount_cents);
        $this->assertDatabaseCount('cash_refunds', 0);
    }

    public function test_waiter_can_correct_and_cancel_before_preparation_without_operating_cash_or_release(): void
    {
        $order = $this->pickup();
        $this->signIn($this->waiter)->get(route('pickup.index'))->assertInertia(fn (Assert $p) => $p
            ->where('orders.0.cashUrl', null)->where('menuUrl', null));
        $this->get(route('operations.edit', $order))->assertOk();
        $context = DiningContext::staff($this->waiter);
        $intent = app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Cliente ajustou o pedido',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]], $context);
        app(OrderFulfillment::class)->confirm($order->id, $intent, $context);
        $this->assertSame(10600, $order->fresh()->total_cents);
        $this->get(route('cash.pickup.account', ['session' => $order->pickup_account_id]))->assertForbidden();
        $this->movePickup($order, 'cancelled', $this->waiter);
        $this->assertDatabaseHas('order_history', ['order_id' => $order->id, 'action' => 'amended', 'actor_id' => $this->waiter->id]);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    private function openPickup(): void
    {
        app(PickupOrders::class)->service(true, 'Abrir retirada de teste', PickupContext::staff($this->manager));
    }

    private function input(array $extra = []): array
    {
        return ['phone' => '(86) 99900-1122', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]], ...$extra];
    }

    private function pickup(?PickupContext $context = null, array $extra = []): DiningOrder
    {
        $this->openPickup();
        $context ??= PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input($extra), $context);

        return app(PickupOrders::class)->confirm($intent->id, $context);
    }

    private function movePickup(DiningOrder $order, string $status, ?User $actor = null, array $extra = []): DiningOrder
    {
        return app(OrderFulfillment::class)->transition($order->id, ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(), 'status' => $status, 'reason' => 'Solicitação registrada', ...$extra], DiningContext::staff($actor ?? $this->manager));
    }

    private function payPickup(DiningOrder $order, array $policy = []): CashPayment
    {
        $context = DiningContext::staff($this->manager);
        $payments = app(CashPayments::class);
        $intent = $payments->preview($order->pickup_account_id, $policy, $context, 'pickup');

        return $payments->confirm($order->pickup_account_id, $intent, ['method' => empty($policy) ? 'pix' : 'cash', 'received' => empty($policy) ? null : '0'], $context, 'pickup');
    }

    private function publicContext(): PickupContext
    {
        $grant = new PickupGrant((string) Str::uuid(), now()->addHours(2)->timestamp);
        $this->withSession(['pickup.visitor' => ['visitor' => $grant->visitor, 'expires' => $grant->expiresAt]]);

        return PickupContext::guest($grant);
    }

    public function test_receiving_starts_paused_and_manager_can_open_with_reason(): void
    {
        $this->assertFalse(DB::table('pickup_settings')->value('accepting_orders'));
        $this->signIn($this->cashier)->post(route('pickup.service'), ['open' => true, 'reason' => 'Abrir operação'])->assertForbidden();
        $this->signIn($this->manager)->post(route('pickup.service'), ['open' => true, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->openPickup();
        $this->assertTrue(DB::table('pickup_settings')->value('accepting_orders'));
        $this->assertDatabaseHas('pickup_events', ['action' => 'receiving_changed', 'actor_id' => $this->manager->id]);
    }

    public function test_paused_service_rejects_preview_and_confirmation(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        app(PickupOrders::class)->service(false, 'Pausa do atendimento', PickupContext::staff($this->manager));
        foreach (['preview', 'confirm'] as $action) {
            try {
                $action === 'preview' ? app(PickupOrders::class)->preview($this->input(), $ctx) : app(PickupOrders::class)->confirm($intent->id, $ctx);
                $this->fail('Paused');
            } catch (ValidationException) {
                $this->assertDatabaseCount('pickup_accounts', 0);
            }
        }
    }

    public function test_pickup_has_own_account_without_fake_table_or_dining_session(): void
    {
        $tables = DB::table('dining_tables')->count();
        $sessions = DB::table('dining_sessions')->count();
        $order = $this->pickup();
        $this->assertSame('pickup', $order->modality);
        $this->assertSame(5300, $order->total_cents);
        $this->assertNull($order->table_id);
        $this->assertNull($order->session_id);
        $this->assertNull($order->intent_id);
        $this->assertNull($order->table_number);
        $this->assertDatabaseCount('dining_tables', $tables);
        $this->assertDatabaseCount('dining_sessions', $sessions);
        $this->assertSame('+5586999001122', PickupAccount::findOrFail($order->pickup_account_id)->phone);
        $this->assertSame($this->cashier->id, $order->actor_id);
        $this->assertSame('staff', $order->source);
    }

    public function test_phone_is_encrypted_and_hidden_from_model_serialization_and_snapshots(): void
    {
        $order = $this->pickup();
        $account = PickupAccount::findOrFail($order->pickup_account_id);
        $intent = PickupIntent::findOrFail($order->pickup_intent_id);
        $this->assertStringNotContainsString('999001122', DB::table('pickup_accounts')->value('phone'));
        $this->assertStringNotContainsString('999001122', DB::table('pickup_intents')->value('phone'));
        $this->assertArrayNotHasKey('phone', $account->toArray());
        $this->assertArrayNotHasKey('phone', $intent->toArray());
        foreach (['order_history', 'print_jobs', 'operations_outbox', 'pickup_events', 'cash_events'] as $table) {
            $this->assertStringNotContainsString('999001122', DB::table($table)->get()->toJson());
        }
    }

    public function test_phone_required_and_malformed_numbers_are_rejected_without_intent(): void
    {
        $this->openPickup();
        $this->signIn($this->cashier);
        foreach (['', '123', '(00) 99999-1111', '(86) 12345-6789', 'abc 86999001122'] as $phone) {
            $this->post(route('pickup.preview'), $this->input(['phone' => $phone]))->assertSessionHasErrors('phone');
        }
        $this->assertDatabaseCount('pickup_intents', 0);
    }

    public function test_phone_with_country_code_and_landline_is_normalized(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        foreach (['+55 (86) 3333-1122' => '+558633331122', '86 99900 1122' => '+5586999001122'] as $raw => $normalized) {
            $intent = app(PickupOrders::class)->preview($this->input(['phone' => $raw]), $ctx);
            $this->assertSame($normalized, $intent->phone);
        }
    }

    public function test_delivery_and_scheduling_fields_are_rejected(): void
    {
        $this->openPickup();
        $this->signIn($this->cashier);
        foreach (['delivery_fee', 'address', 'scheduled_at'] as $key) {
            $this->post(route('pickup.preview'), $this->input([$key => 'not allowed']))->assertSessionHasErrors($key);
        }
    }

    public function test_confirmation_is_idempotent_and_creates_one_history_and_two_tickets(): void
    {
        $order = $this->pickup();
        $again = app(PickupOrders::class)->confirm($order->pickup_intent_id, PickupContext::staff($this->cashier));
        $this->assertSame($order->id, $again->id);
        $this->assertDatabaseCount('dining_orders', 1);
        $this->assertDatabaseCount('pickup_accounts', 1);
        $this->assertDatabaseCount('order_history', 1);
        $this->assertDatabaseCount('print_jobs', 2);
    }

    public function test_catalog_price_change_requires_new_preview_without_partial_order(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->variant->forceFill(['price_cents' => 9900])->save();
        try {
            app(PickupOrders::class)->confirm($intent->id, $ctx);
            $this->fail('Changed');
        } catch (ValidationException) {
            $this->assertDatabaseCount('pickup_accounts', 0);
            $this->assertDatabaseCount('dining_orders', 0);
        }
    }

    public function test_unavailable_variant_prevents_confirmation(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->variant->forceFill(['is_available' => false])->save();
        $this->expectException(ValidationException::class);
        app(PickupOrders::class)->confirm($intent->id, $ctx);
    }

    public function test_expired_preview_requires_new_quote(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->travel(11)->minutes();
        $this->expectException(ValidationException::class);
        app(PickupOrders::class)->confirm($intent->id, $ctx);
    }

    public function test_another_staff_actor_cannot_confirm_preview(): void
    {
        $this->openPickup();
        $intent = app(PickupOrders::class)->preview($this->input(), PickupContext::staff($this->cashier));
        $this->signIn($this->manager)->post(route('pickup.confirm', $intent->id))->assertNotFound();
        $this->assertDatabaseCount('dining_orders', 0);
    }

    public function test_revoked_actor_cannot_confirm_with_old_context(): void
    {
        $this->openPickup();
        $ctx = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->cashier->increment('auth_version');
        $this->expectException(AuthorizationException::class);
        app(PickupOrders::class)->confirm($intent->id, $ctx);
    }

    public function test_guest_has_no_staff_credit_and_browser_grant_owns_the_receipt(): void
    {
        $ctx = $this->publicContext();
        $order = $this->pickup($ctx);
        $this->assertSame('web', $order->source);
        $this->assertNull($order->actor_id);
        $url = route('pickup.public.receipt', $order->public_id);
        $this->get($url)->assertInertia(fn (Assert $p) => $p->component('public/pickup/receipt')->where('auth.user', null)->where('order.locationLabel', $order->locationLabel())->missing('order.phone')->missing('order.client_hash'));
        $this->publicContext();
        $this->getJson($url)->assertNotFound();
    }

    public function test_public_menu_hides_staff_identity_even_in_authenticated_browser(): void
    {
        $this->signIn($this->manager)->get(route('pickup.public.menu'))->assertInertia(fn (Assert $p) => $p->component('public/pickup/menu')->where('auth.user', null)->has('navigation', 0)->where('table', null)->where('canManualDiscount', false));
    }

    public function test_public_review_exposes_only_last_four_digits(): void
    {
        $this->openPickup();
        $ctx = $this->publicContext();
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->get(route('pickup.public.review', $intent->id))->assertInertia(fn (Assert $p) => $p->where('contactHint', 'Contato terminado em 1122')->missing('phone')->missing('client_hash'));
    }

    public function test_guest_cannot_use_manual_discount_or_manage_receiving(): void
    {
        $this->openPickup();
        $this->get(route('pickup.public.menu'));
        $this->post(route('pickup.public.preview'), $this->input(['items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'manual_discount' => ['type' => 'percentage', 'value' => '10']]]]))->assertForbidden();
        $this->post(route('pickup.service'), ['open' => true, 'reason' => 'Guest attempt'])->assertRedirect(route('login'));
    }

    public function test_guest_confirmation_rate_limit_prevents_fourth_new_order_but_allows_replay(): void
    {
        $ctx = $this->publicContext();
        $first = $this->pickup($ctx);
        $this->pickup($ctx);
        $this->pickup($ctx);
        $this->assertSame($first->id, app(PickupOrders::class)->confirm($first->pickup_intent_id, $ctx)->id);
        $intent = app(PickupOrders::class)->preview($this->input(), $ctx);
        $this->expectException(ValidationException::class);
        app(PickupOrders::class)->confirm($intent->id, $ctx);
    }

    public function test_courier_cannot_access_pickup_or_change_its_items(): void
    {
        $order = $this->pickup();
        foreach ([User::factory()->create(['role' => 'entregador'])] as $actor) {
            $this->signIn($actor)->get(route('pickup.index'))->assertForbidden();
            $this->get(route('operations.show', $order))->assertForbidden();
            $this->get(route('operations.edit', $order))->assertForbidden();
            $this->post(route('operations.preview', $order), ['version' => 1, 'reason' => 'Alterar pedido', 'items' => $this->input()['items']])->assertForbidden();
        }
    }

    public function test_kitchen_sees_pickup_without_phone_prices_or_commercial_history(): void
    {
        $order = $this->pickup();
        $this->signIn(User::factory()->create(['role' => 'cozinha']))->get(route('operations.show', $order))->assertInertia(fn (Assert $p) => $p->where('phone', null)->where('cashUrl', null)->where('order.total', null)->where('order.editUrl', null)->has('history', 0)->missing('order.items.0.total'));
        $this->get(route('workspace.kitchen', ['modality' => 'pickup']))->assertInertia(fn (Assert $p) => $p->has('orders', 1)->where('orders.0.locationLabel', $order->locationLabel()));
    }

    public function test_cashier_board_contains_contact_and_generic_account_has_no_table(): void
    {
        $order = $this->pickup();
        $this->signIn($this->cashier)->get(route('pickup.index'))->assertInertia(fn (Assert $p) => $p->where('orders.0.phone', '+5586999001122'));
        $this->get(route('cash.pickup.account', ['session' => $order->pickup_account_id]))->assertInertia(fn (Assert $p) => $p->where('account.table', null)->where('account.closeUrl', null)->where('canPay', true));
        $this->get(route('operations.edit', $order))->assertInertia(fn (Assert $p) => $p->where('table', null)->has('initialItems', 1));
    }

    public function test_ready_order_cannot_be_collected_without_full_payment(): void
    {
        $order = $this->pickup();
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        try {
            $this->movePickup($order, 'collected', $this->cashier);
            $this->fail('Unpaid');
        } catch (ValidationException) {
            $this->assertSame('ready', $order->fresh()->status);
            $this->assertNull(PickupAccount::findOrFail($order->pickup_account_id)->closed_at);
        }
    }

    public function test_full_workflow_collects_after_payment_and_closes_only_pickup_account(): void
    {
        $salon = $this->order();
        $order = $this->pickup();
        $kitchen = User::factory()->create(['role' => 'cozinha']);
        $this->movePickup($order, 'preparing', $kitchen);
        $this->movePickup($order, 'ready', $kitchen);
        $payment = $this->payPickup($order);
        $result = $this->movePickup($order, 'collected', $this->cashier);
        $this->assertSame('collected', $result->status);
        $this->assertNotNull($result->collected_at);
        $this->assertNotNull(PickupAccount::findOrFail($order->pickup_account_id)->closed_at);
        $this->assertNull($salon->session->fresh()->settled_at);
        $this->assertNull($salon->session->fresh()->closed_at);
        $this->assertNull($payment->session_id);
        $this->assertSame($order->pickup_account_id, $payment->pickup_account_id);
    }

    public function test_steps_cannot_be_skipped_or_cross_to_served(): void
    {
        $order = $this->pickup();
        foreach (['ready', 'collected', 'served'] as $status) {
            try {
                $this->movePickup($order, $status);
                $this->fail('Skip');
            } catch (ValidationException) {
                $this->assertSame('confirmed', $order->fresh()->status);
            }
        }
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        $this->expectException(ValidationException::class);
        $this->movePickup($order, 'served');
    }

    public function test_kitchen_and_waiter_cannot_release_pickup_even_if_paid(): void
    {
        $order = $this->pickup();
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        $this->payPickup($order);
        foreach ([$this->waiter, User::factory()->create(['role' => 'cozinha'])] as $actor) {
            $this->signIn($actor)->post(route('operations.transition', $order), ['version' => 3, 'operation_id' => (string) Str::uuid(), 'status' => 'collected'])->assertForbidden();
        }
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_repeated_collect_command_is_idempotent_and_stale_command_is_rejected(): void
    {
        $order = $this->pickup();
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        $this->payPickup($order);
        $op = (string) Str::uuid();
        $this->movePickup($order, 'collected', $this->cashier, ['version' => 3, 'operation_id' => $op]);
        $this->movePickup($order, 'collected', $this->cashier, ['version' => 3, 'operation_id' => $op]);
        $this->assertDatabaseCount('order_history', 4);
        $this->expectException(ValidationException::class);
        $this->movePickup($order, 'collected', $this->cashier, ['version' => 3]);
    }

    public function test_payment_intents_do_not_cross_account_modalities_even_with_same_numeric_id(): void
    {
        $pickup = $this->pickup();
        DB::select("select setval(pg_get_serial_sequence('dining_sessions', 'id'), ?, false)", [$pickup->pickup_account_id]);
        $salon = $this->order();
        $this->assertSame($salon->session_id, $pickup->pickup_account_id);
        $ctx = DiningContext::staff($this->cashier);
        $payments = app(CashPayments::class);
        $intent = $payments->preview($pickup->pickup_account_id, [], $ctx, 'pickup');
        $this->signIn($this->cashier)->post(route('cash.confirm', ['session' => $salon->session_id, 'intent' => $intent]), ['method' => 'pix'])->assertNotFound();
        $payment = $payments->confirm($pickup->pickup_account_id, $intent, ['method' => 'pix'], $ctx, 'pickup');
        $this->assertSame($pickup->pickup_account_id, BillingAccounts::forPayment($payment)->id);
        $this->assertInstanceOf(PickupAccount::class, BillingAccounts::forPayment($payment));
        $this->assertSame(5300, $this->pay($salon)->amount_cents);
        $this->assertDatabaseCount('cash_payments', 2);
    }

    public function test_zero_amount_still_requires_explicit_payment_before_collection(): void
    {
        $order = $this->pickup();
        $payment = $this->payPickup($order, ['discount' => ['type' => 'percentage', 'value' => '100', 'reason' => 'Cortesia aprovada']]);
        $this->assertSame(0, $payment->amount_cents);
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        $this->movePickup($order, 'collected', $this->cashier);
        $this->assertSame('collected', $order->fresh()->status);
    }

    public function test_paid_reduction_requires_refund_before_collect_and_original_receipt_survives(): void
    {
        $order = $this->pickup(extra: ['items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]]);
        $payment = $this->payPickup($order);
        $original = $payment->fresh()->snapshot;
        $f = app(OrderFulfillment::class);
        $intent = $f->preview($order->id, ['version' => 1, 'reason' => 'Reduzir quantidade', 'items' => $this->input()['items']], DiningContext::staff($this->cashier));
        $f->confirm($order->id, $intent, DiningContext::staff($this->cashier));
        $this->movePickup($order, 'preparing');
        $this->movePickup($order, 'ready');
        try {
            $this->movePickup($order, 'collected');
            $this->fail('Refund pending');
        } catch (ValidationException) {
            $this->assertTrue($payment->fresh()->has_pending_refund);
        }
        app(CashPayments::class)->refund($payment->id, ['operation_id' => (string) Str::uuid(), 'method' => 'pix', 'amount' => '53', 'reason' => 'Devolução realizada', 'performed' => true], DiningContext::staff($this->manager));
        $this->movePickup($order, 'collected', $this->cashier);
        $this->assertSame($original, $payment->fresh()->snapshot);
        $this->assertFalse($payment->fresh()->has_pending_refund);
    }

    public function test_paid_order_increase_is_rejected_without_change(): void
    {
        $order = $this->pickup();
        $this->payPickup($order);
        $this->signIn($this->cashier)->post(route('operations.preview', $order), ['version' => 1, 'reason' => 'Aumentar pedido', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]])->assertSessionHasErrors('operation');
        $this->assertSame(5300, $order->fresh()->total_cents);
    }

    public function test_cancel_before_prepare_closes_operational_account_without_fake_payment(): void
    {
        $order = $this->pickup();
        $this->movePickup($order, 'cancelled', $this->cashier);
        $account = PickupAccount::findOrFail($order->pickup_account_id);
        $this->assertNotNull($account->closed_at);
        $this->assertNull($account->settled_at);
        $this->assertDatabaseCount('cash_payments', 0);
        $this->assertDatabaseCount('print_jobs', 4);
    }

    public function test_only_manager_cancels_after_prepare_and_paid_cancellation_remains_in_refund_queue(): void
    {
        $order = $this->pickup();
        $payment = $this->payPickup($order);
        $this->movePickup($order, 'preparing');
        $this->signIn($this->cashier)->post(route('operations.transition', $order), ['version' => 2, 'operation_id' => (string) Str::uuid(), 'status' => 'cancelled', 'reason' => 'Cancelar depois'])->assertForbidden();
        $this->movePickup($order, 'cancelled');
        $this->assertTrue($payment->fresh()->has_pending_refund);
        $this->assertSame(5300, app(AccountBalance::class)->forAccount(BillingAccounts::forOrder($order))['refund_due_cents']);
        $this->signIn($this->manager)->get(route('cash.index'))->assertInertia(fn (Assert $p) => $p->has('pending', 1)->where('pending.0.url', route('cash.pickup.account', ['session' => $order->pickup_account_id])));
    }

    public function test_tickets_identify_pickup_and_never_include_phone_or_kitchen_prices(): void
    {
        $order = $this->pickup();
        foreach (DB::table('print_jobs')->get() as $job) {
            $snapshot = json_decode($job->snapshot, true);
            $this->assertSame($order->locationLabel(), $snapshot['locationLabel']);
            $this->assertNull($snapshot['table']);
            $this->assertStringNotContainsString('999001122', $job->snapshot);
            if ($job->target === 'kitchen') {
                $this->assertStringNotContainsString('cents', $job->snapshot);
            }
        }
    }

    public function test_guest_websocket_authorization_is_exact_owned_and_expires(): void
    {
        config(['broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        $ctx = $this->publicContext();
        $order = $this->pickup($ctx);
        $url = route('pickup.public.realtime', $order->public_id);
        $input = ['channel_name' => 'private-pickup.order.'.$order->public_id, 'socket_id' => '123.456'];
        $this->postJson($url, $input)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson($url, [...$input, 'channel_name' => 'private-operations.pickup'])->assertForbidden();
        $this->travel(121)->minutes();
        $this->postJson($url, $input)->assertForbidden();
    }

    public function test_outbox_uses_pickup_channel_and_minimal_payload(): void
    {
        $order = $this->pickup();
        DB::table('operations_outbox')->whereNull('order_reference')->delete();
        $connection = \Mockery::mock(Broadcaster::class);
        $connection->shouldReceive('broadcast')->once()->with(\Mockery::on(fn ($channels) => in_array('private-pickup.order.'.$order->public_id, $channels, true) && ! in_array('private-dining.order.'.$order->public_id, $channels, true)), 'operations.changed', \Mockery::on(fn ($payload) => array_keys($payload) === ['id']));
        Broadcast::shouldReceive('connection')->with('reverb')->once()->andReturn($connection);
        $this->assertSame(1, app(OperationsChanges::class)->publish());
    }

    public function test_pickup_staff_channel_allows_waiter_and_denies_kitchen(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::purge();
        require base_path('routes/channels.php');
        $data = ['channel_name' => 'private-operations.pickup', 'socket_id' => '123.456'];
        $this->signIn($this->cashier)->postJson('/broadcasting/auth', $data)->assertOk();
        $this->signIn($this->waiter)->postJson('/broadcasting/auth', $data)->assertOk();
        $this->signIn(User::factory()->create(['role' => 'cozinha']))->postJson('/broadcasting/auth', $data)->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Delivery;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\CashPayments;
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryCoverage;
use App\Application\Delivery\DeliveryDispatch;
use App\Application\Delivery\DeliveryGrant;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderFulfillment;
use App\Models\CashPayment;
use App\Models\DeliveryAccount;
use App\Models\DeliveryIntent;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class DeliveryOrdersTest extends CashTestCase
{
    private User $courier;

    private int $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->courier = User::factory()->create(['role' => 'entregador']);
        $this->area = app(DeliveryCoverage::class)->save(null, $this->areaInput(), DiningContext::staff($this->manager));
        app(DeliveryCoverage::class)->service(true, 'Operação de teste', DiningContext::staff($this->manager));
    }

    private function areaInput(array $extra = []): array
    {
        return ['name' => 'Área de teste', 'city' => 'Cidade Teste', 'state' => 'PI', 'neighborhood' => 'Centro',
            'cep_start' => '64000-000', 'cep_end' => '64000-999', 'fee' => '7,00', 'minimum' => '40,00',
            'is_active' => true, 'reason' => 'Configuração de teste', ...$extra];
    }

    private function input(array $extra = []): array
    {
        return ['phone' => '(86) 99900-1122', 'address' => ['recipient' => 'Pessoa de Teste', 'street' => 'Rua de Teste', 'number' => '10',
            'complement' => '', 'neighborhood' => 'Centro', 'city' => 'Cidade Teste', 'state' => 'PI', 'cep' => '64000-000'],
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]], ...$extra];
    }

    private function delivery(?DeliveryContext $context = null, array $extra = []): DiningOrder
    {
        $context ??= DeliveryContext::staff($this->cashier);
        $intent = app(DeliveryOrders::class)->preview($this->input($extra), $context);

        return app(DeliveryOrders::class)->confirm($intent->id, $context);
    }

    private function move(DiningOrder $order, string $status, ?User $user = null, array $extra = []): DiningOrder
    {
        return app(OrderFulfillment::class)->transition($order->id, ['version' => $order->fresh()->version,
            'operation_id' => (string) Str::uuid(), 'status' => $status, 'reason' => 'Motivo de teste', ...$extra], DiningContext::staff($user ?? $this->manager));
    }

    private function dispatch(DiningOrder $order): void
    {
        $this->move($order, 'preparing');
        $this->move($order, 'ready');
        app(DeliveryDispatch::class)->assign($order->id, ['courier_id' => $this->courier->id, 'operation_id' => (string) Str::uuid(),
            'version' => $order->fresh()->version, 'reason' => 'Atribuição de teste'], DiningContext::staff($this->cashier));
        $this->move($order, 'waiting_courier', $this->cashier);
        $this->move($order, 'dispatched', $this->courier);
    }

    private function payDelivery(DiningOrder $order, string $method = 'pix'): CashPayment
    {
        $context = DiningContext::staff($this->courier);
        $payments = app(CashPayments::class);
        $intent = $payments->preview($order->delivery_account_id, [], $context, 'delivery');

        return $payments->confirm($order->delivery_account_id, $intent, ['method' => $method,
            'received' => $method === 'cash' ? '100,00' : null], $context, 'delivery');
    }

    public function test_delivery_has_own_account_fee_and_idempotent_confirmation(): void
    {
        $context = DeliveryContext::staff($this->cashier);
        $intent = app(DeliveryOrders::class)->preview($this->input(), $context);
        $this->assertSame(6000, $intent->total_cents);
        $order = app(DeliveryOrders::class)->confirm($intent->id, $context);
        $this->assertSame($order->id, app(DeliveryOrders::class)->confirm($intent->id, $context)->id);
        $this->assertSame(5300, $order->total_cents);
        $this->assertNull($order->table_id);
        $this->assertNull($order->pickup_account_id);
        $account = DeliveryAccount::findOrFail($order->delivery_account_id);
        $this->assertSame(6000, app(AccountBalance::class)->forAccount($account)['total_cents']);
        $this->assertDatabaseCount('dining_sessions', 0);
        $this->assertDatabaseCount('delivery_accounts', 1);
        $this->assertDatabaseCount('print_jobs', 2);
        $this->assertDatabaseHas('dining_orders', ['id' => $order->id, 'cash_shift_id' => $this->shift->id, 'modality' => 'delivery']);
    }

    public function test_phone_and_address_are_encrypted_and_absent_from_history_print_and_events(): void
    {
        $order = $this->delivery();
        $account = DeliveryAccount::findOrFail($order->delivery_account_id);
        $this->assertSame('+5586999001122', $account->phone);
        $this->assertSame('Rua de Teste', $account->address['street']);
        foreach (['delivery_accounts', 'delivery_intents', 'order_history', 'print_jobs', 'operations_outbox', 'delivery_events'] as $table) {
            $data = DB::table($table)->get()->toJson();
            $this->assertStringNotContainsString('999001122', $data);
            $this->assertStringNotContainsString('Rua de Teste', $data);
        }
        $this->assertArrayNotHasKey('phone', $account->toArray());
        $this->assertArrayNotHasKey('address', $account->toArray());
        $print = json_decode(DB::table('print_jobs')->where('target', 'cashier')->value('snapshot'), true);
        $this->assertSame(700, $print['delivery_fee_cents']);
        $this->assertSame(6000, $print['total_cents']);
        $this->assertArrayNotHasKey('delivery_fee_cents', json_decode(DB::table('print_jobs')->where('target', 'kitchen')->value('snapshot'), true));
    }

    public function test_postal_boundaries_are_inclusive_and_city_and_neighborhood_must_also_match(): void
    {
        foreach (['64000-000', '64000999'] as $cep) {
            $input = $this->input();
            $input['address']['cep'] = $cep;
            $input['address']['neighborhood'] = 'céntro';
            $this->assertSame(6000, app(DeliveryOrders::class)->preview($input, DeliveryContext::staff($this->cashier))->total_cents);
        }
        foreach (['cep' => '64001000', 'neighborhood' => 'Outro bairro', 'city' => 'Outra cidade', 'state' => 'CE'] as $key => $value) {
            $input = $this->input();
            $input['address'][$key] = $value;
            try {
                app(DeliveryOrders::class)->preview($input, DeliveryContext::staff($this->cashier));
                $this->fail('Fora da cobertura');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('address', $e->errors());
            }
        }
        $this->assertDatabaseCount('delivery_accounts', 0);
    }

    public function test_minimum_is_after_discounts_and_does_not_include_fee(): void
    {
        app(DeliveryCoverage::class)->save($this->area, $this->areaInput(['version' => 1, 'minimum' => '54,00', 'fee' => '99,00']), DiningContext::staff($this->manager));
        $this->expectException(ValidationException::class);
        $this->delivery();
    }

    public function test_manual_discount_cannot_make_items_fall_below_minimum(): void
    {
        $input = $this->input(['items' => [['variant_id' => $this->variant->id, 'quantity' => 1,
            'manual_discount' => ['type' => 'percentage', 'value' => '50,00']]]]);
        $this->expectException(ValidationException::class);
        app(DeliveryOrders::class)->preview($input, DeliveryContext::staff($this->manager));
    }

    public function test_coverage_change_requires_new_preview_and_does_not_reprice_confirmed_delivery(): void
    {
        $confirmed = $this->delivery();
        $context = DeliveryContext::staff($this->cashier);
        $intent = app(DeliveryOrders::class)->preview($this->input(), $context);
        app(DeliveryCoverage::class)->save($this->area, $this->areaInput(['version' => 1, 'fee' => '9,00']), DiningContext::staff($this->manager));
        try {
            app(DeliveryOrders::class)->confirm($intent->id, $context);
            $this->fail('Prévia antiga');
        } catch (ValidationException) {
            $this->assertDatabaseCount('delivery_accounts', 1);
        }
        $this->assertSame(6000, app(AccountBalance::class)->forAccount(DeliveryAccount::findOrFail($confirmed->delivery_account_id))['total_cents']);
    }

    public function test_expired_preview_and_paused_receiving_fail_without_partial_order(): void
    {
        $context = DeliveryContext::staff($this->cashier);
        $intent = app(DeliveryOrders::class)->preview($this->input(), $context);
        $intent->forceFill(['expires_at' => now()])->save();
        try {
            app(DeliveryOrders::class)->confirm($intent->id, $context);
            $this->fail('Expired');
        } catch (ValidationException) {
            $this->assertDatabaseCount('dining_orders', 0);
        }
        app(DeliveryCoverage::class)->service(false, 'Pausa de teste', DiningContext::staff($this->manager));
        $this->expectException(ValidationException::class);
        app(DeliveryOrders::class)->preview($this->input(), $context);
    }

    public function test_overlapping_active_rules_are_rejected_and_inactive_drafts_are_allowed(): void
    {
        try {
            app(DeliveryCoverage::class)->save(null, $this->areaInput(['neighborhood' => '', 'cep_start' => '64000999', 'cep_end' => '64002000']), DiningContext::staff($this->manager));
            $this->fail('Overlap');
        } catch (ValidationException) {
            $this->assertDatabaseCount('delivery_areas', 1);
        }
        app(DeliveryCoverage::class)->save(null, $this->areaInput(['is_active' => false]), DiningContext::staff($this->manager));
        app(DeliveryCoverage::class)->save(null, $this->areaInput(['cep_start' => '64001000', 'cep_end' => '64001999']), DiningContext::staff($this->manager));
        $this->assertDatabaseCount('delivery_areas', 3);
    }

    public function test_only_manager_changes_areas_and_stale_version_is_rejected(): void
    {
        $this->signIn($this->cashier)->post(route('delivery.areas.store'), $this->areaInput())->assertForbidden();
        $this->signIn($this->manager)->put(route('delivery.areas.update', $this->area), $this->areaInput(['version' => 99]))->assertSessionHasErrors('coverage');
        $this->assertSame(700, DB::table('delivery_areas')->value('fee_cents'));
    }

    public function test_complete_delivery_requires_assigned_courier_and_integral_payment(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        try {
            $this->move($order, 'delivered', $this->courier);
            $this->fail('Unpaid');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->delivered_at);
        }
        $payment = $this->payDelivery($order, 'cash');
        $this->assertSame(6000, $payment->amount_cents);
        $this->assertSame(4000, $payment->change_cents);
        $command = ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(), 'status' => 'delivered'];
        $service = app(OrderFulfillment::class);
        $service->transition($order->id, $command, DiningContext::staff($this->courier));
        $service->transition($order->id, $command, DiningContext::staff($this->courier));
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull(DeliveryAccount::findOrFail($order->delivery_account_id)->closed_at);
        $this->assertDatabaseCount('cash_payments', 1);
    }

    public function test_other_courier_cannot_read_receive_or_transition_assignment(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $other = User::factory()->create(['role' => 'entregador']);
        $this->signIn($other)->get(route('delivery.show', $order))->assertForbidden();
        $this->get(route('cash.delivery.account', ['session' => $order->delivery_account_id]))->assertForbidden();
        $this->post(route('operations.transition', $order), ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(), 'status' => 'delivered'])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(CashPayments::class)->preview($order->delivery_account_id, [], DiningContext::staff($other), 'delivery');
    }

    public function test_courier_sees_only_own_orders_and_has_no_global_cash_or_preparation_powers(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $this->delivery();
        $this->signIn($this->courier)->get(route('delivery.index'))->assertInertia(fn (Assert $p) => $p->has('orders', 1)->where('all', false));
        $this->get(route('cash.index'))->assertForbidden();
        $this->get(route('workspace.kitchen'))->assertForbidden();
        $this->get(route('delivery.areas.index'))->assertForbidden();
        $this->get(route('delivery.menu'))->assertForbidden();
    }

    public function test_payment_before_dispatch_and_cashier_payment_for_delivery_are_denied(): void
    {
        $order = $this->delivery();
        foreach ([$this->courier, $this->cashier] as $actor) {
            try {
                app(CashPayments::class)->preview($order->delivery_account_id, [], DiningContext::staff($actor), 'delivery');
                $this->fail('Not assigned/dispatched');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('cash_payments', 0);
            }
        }
    }

    public function test_no_state_skips_or_stale_transition(): void
    {
        $order = $this->delivery();
        foreach ([['status' => 'ready', 'version' => 1], ['status' => 'preparing', 'version' => 99]] as $command) {
            try {
                $this->move($order, $command['status'], extra: $command);
                $this->fail('Invalid transition');
            } catch (ValidationException) {
                $this->assertSame('confirmed', $order->fresh()->status);
            }
        }
    }

    public function test_paid_cancellation_refunds_items_and_fee_without_rewriting_original_payment(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $payment = $this->payDelivery($order);
        $this->move($order, 'delivered', $this->courier);
        $deliveredAt = $order->fresh()->delivered_at;
        $this->move($order, 'cancelled');
        $account = DeliveryAccount::findOrFail($order->delivery_account_id);
        $this->assertSame(6000, app(AccountBalance::class)->forAccount($account)['refund_due_cents']);
        $this->assertSame(6000, $payment->fresh()->amount_cents);
        $this->assertSame($deliveredAt->timestamp, $order->fresh()->delivered_at->timestamp);
        $ticket = json_decode(DB::table('print_jobs')->where('target', 'cashier')->where('kind', 'cancellation')->value('snapshot'), true);
        $this->assertSame(6000, $ticket['total_cents']);
        $this->assertSame(700, $ticket['delivery_fee_cents']);
        app(CashPayments::class)->refund($payment->id, ['operation_id' => (string) Str::uuid(), 'method' => 'pix', 'amount' => '60,00',
            'performed' => true, 'reason' => 'Devolução efetuada no teste'], DiningContext::staff($this->manager));
        $this->assertSame(0, app(AccountBalance::class)->forAccount($account)['refund_due_cents']);
    }

    public function test_preparation_hides_address_from_kitchen_and_waiter_can_edit_without_contact_access(): void
    {
        $order = $this->delivery();
        $kitchen = User::factory()->create(['role' => 'cozinha']);
        $this->signIn($kitchen)->get(route('operations.show', $order))->assertInertia(fn (Assert $p) => $p->where('phone', null)->where('history', []));
        $this->get(route('delivery.show', $order))->assertForbidden();
        $this->signIn($this->waiter)->get(route('delivery.show', $order))->assertInertia(fn (Assert $p) => $p->where('contact', null)->where('cashUrl', null));
        $this->get(route('operations.edit', $order))->assertOk();
    }

    public function test_guest_tracking_is_private_and_does_not_expose_staff_identity(): void
    {
        $grant = new DeliveryGrant((string) Str::uuid(), now()->addHours(2)->timestamp);
        $context = DeliveryContext::guest($grant);
        $order = $this->delivery($context);
        $this->withSession(['delivery.visitor' => ['visitor' => $grant->visitor, 'expires' => $grant->expiresAt]]);
        $this->get(route('delivery.public.receipt', $order->public_id))->assertInertia(fn (Assert $p) => $p->where('auth.user', null)
            ->where('order.total', 'R$ 60,00')->where('order.deliveryFee', 'R$ 7,00')->missing('order.address'));
        $this->withSession(['delivery.visitor' => ['visitor' => (string) Str::uuid(), 'expires' => $grant->expiresAt]]);
        $this->getJson(route('delivery.public.receipt', $order->public_id))->assertNotFound();
        $this->assertArrayNotHasKey('address', DeliveryIntent::findOrFail($order->delivery_intent_id)->toArray());
    }

    public function test_address_phone_and_injected_fee_validation_fail_without_order(): void
    {
        $this->signIn($this->cashier)->post(route('delivery.preview'), $this->input(['phone' => '123']))->assertSessionHasErrors('phone');
        $this->post(route('delivery.preview'), $this->input(['address' => []]))->assertSessionHasErrors('address');
        $this->post(route('delivery.preview'), $this->input(['delivery_fee' => 0]))->assertSessionHasErrors('delivery_fee');
        $this->assertDatabaseCount('dining_orders', 0);
    }

    public function test_invalid_uf_or_cep_and_partial_coverage_range_are_rejected(): void
    {
        $input = $this->input();
        $input['address']['state'] = 'ZZ';
        $this->signIn($this->cashier)->post(route('delivery.preview'), $input)->assertSessionHasErrors('address.state');
        $input['address']['state'] = 'pi';
        $input['address']['cep'] = '64000';
        $this->post(route('delivery.preview'), $input)->assertSessionHasErrors('address.cep');
        $this->expectException(ValidationException::class);
        app(DeliveryCoverage::class)->save(null, $this->areaInput(['cep_end' => '']), DiningContext::staff($this->manager));
    }

    public function test_payment_replay_is_safe_after_delivery_and_reassignment_is_blocked_after_payment(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $context = DiningContext::staff($this->courier);
        $service = app(CashPayments::class);
        $id = $service->preview($order->delivery_account_id, [], $context, 'delivery');
        $payment = $service->confirm($order->delivery_account_id, $id, ['method' => 'card'], $context, 'delivery');
        $this->move($order, 'delivered', $this->courier);
        $this->assertSame($payment->id, $service->confirm($order->delivery_account_id, $id, ['method' => 'card'], $context, 'delivery')->id);
        $this->expectException(ValidationException::class);
        app(DeliveryDispatch::class)->assign($order->id, ['courier_id' => $this->courier->id, 'operation_id' => (string) Str::uuid(),
            'version' => $order->fresh()->version, 'reason' => 'Troca após pagamento'], DiningContext::staff($this->manager));
    }

    public function test_assignment_replay_and_revocation_deny_old_courier(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $other = User::factory()->create(['role' => 'entregador']);
        $command = ['courier_id' => $other->id, 'operation_id' => (string) Str::uuid(),
            'version' => $order->fresh()->version, 'reason' => 'Substituir entregador'];
        app(DeliveryDispatch::class)->assign($order->id, $command, DiningContext::staff($this->manager));
        app(DeliveryDispatch::class)->assign($order->id, $command, DiningContext::staff($this->manager));
        $this->assertSame($other->id, DeliveryAccount::findOrFail($order->delivery_account_id)->courier_id);
        $this->signIn($this->courier)->get(route('delivery.show', $order))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(CashPayments::class)->preview($order->delivery_account_id, [], DiningContext::staff($this->courier), 'delivery');
    }

    public function test_role_change_does_not_keep_old_delivery_assignment_authority(): void
    {
        $order = $this->delivery();
        $this->dispatch($order);
        $this->courier->forceFill(['role' => 'garcom', 'auth_version' => $this->courier->auth_version + 1])->save();
        $this->signIn($this->courier)->get(route('delivery.show', $order))->assertInertia(fn (Assert $p) => $p->where('contact', null)->where('next', null));
        $this->expectException(AuthorizationException::class);
        $this->move($order, 'delivered', $this->courier);
    }
}

<?php

namespace Tests\Feature\Reports;

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashReconciliation;
use App\Application\Cash\CashShifts;
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryCoverage;
use App\Application\Delivery\DeliveryDispatch;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Application\Reports\ShiftReportData;
use App\Application\Reports\ShiftReports;
use App\Domain\Catalog\Money;
use App\Models\CashShift;
use App\Models\DiningOrder;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class ShiftReportsTest extends CashTestCase
{
    private function report(?int $id = null): array
    {
        return app(ShiftReports::class)->read($id ?? $this->shift->id, DiningContext::staff($this->manager));
    }

    private function mode(array $report, string $mode = 'dining_room'): array
    {
        return collect($report['data']['modalities'])->firstWhere('modality', $mode);
    }

    private function waiterOrder(?User $user = null, ?DiningTable $table = null): DiningOrder
    {
        $table ??= $this->table;
        $context = DiningContext::staff($user ?? $this->waiter);
        $intent = app(DiningOrders::class)->preview($table->id, ['cycle' => $table->fresh()->cycle,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $context);

        return app(DiningOrders::class)->confirm($intent->id, $table->id, $context);
    }

    private function closeShift(?array $counted = null, ?array $extra = null): CashShift
    {
        $totals = app(CashReconciliation::class)->totals($this->shift);
        $counted ??= array_map(fn ($row) => Money::decimal($row['expected']), $totals);

        return app(CashShifts::class)->close($this->shift->id, ['operation_id' => (string) Str::uuid(),
            'version' => $this->shift->fresh()->version, 'counted' => $counted, ...($extra ?? [])], DiningContext::staff($this->cashier));
    }

    private function cancel(DiningOrder $order): void
    {
        app(OrderFulfillment::class)->transition($order->id, ['version' => $order->fresh()->version,
            'operation_id' => (string) Str::uuid(), 'status' => 'cancelled', 'reason' => 'Cancelamento de teste'], DiningContext::staff($this->manager));
    }

    public function test_same_table_counts_once_per_waiter_and_qr_has_no_waiter_credit(): void
    {
        $first = $this->waiterOrder();
        $this->waiterOrder();
        $other = User::factory()->create(['role' => 'garcom']);
        $this->waiterOrder($other);
        $context = DiningContext::guest($this->grant());
        $intent = $this->preview($context);
        app(DiningOrders::class)->confirm($intent->id, $this->table->id, $context);
        $this->cancel($first);
        $report = $this->report();
        $waiters = collect($report['data']['waiters'])->keyBy('actor_id');
        $this->assertSame(1, $waiters[$this->waiter->id]['tables']);
        $this->assertSame(2, $waiters[$this->waiter->id]['orders']);
        $this->assertSame(1, $waiters[$this->waiter->id]['cancelled']);
        $this->assertSame(1, $waiters[$other->id]['tables']);
        $this->assertSame(1, $report['data']['qr_orders']);
        $this->assertSame(4, $this->mode($report)['orders']);
        $this->assertSame(1, $this->mode($report)['cancelled']);
        $this->assertSame(5300, $this->mode($report)['cancelled_cents']);
    }

    public function test_different_tables_and_role_at_order_are_preserved_when_role_changes(): void
    {
        $this->waiterOrder();
        $table = app(ManageDining::class)->createTable(['number' => 8], DiningContext::staff($this->manager));
        $this->waiterOrder(table: $table);
        $this->waiterOrder($this->manager);
        $this->waiter->forceFill(['role' => 'cozinha', 'auth_version' => $this->waiter->auth_version + 1])->save();
        $report = $this->report();
        $this->assertCount(1, $report['data']['waiters']);
        $this->assertSame(2, $report['data']['waiters'][0]['tables']);
        $this->assertSame(2, $report['data']['waiters'][0]['orders']);
        $this->assertSame(3, $this->mode($report)['orders']);
        $this->assertDatabaseHas('dining_orders', ['actor_id' => $this->waiter->id, 'actor_role' => 'garcom']);
    }

    public function test_sales_show_payment_discount_and_refund_separately_from_order_counts(): void
    {
        $order = $this->waiterOrder();
        $this->pay($order, 'pix', $this->manager, ['discount' => ['type' => 'percentage', 'value' => '10', 'reason' => 'Desconto de teste']]);
        $mode = $this->mode($this->report());
        $this->assertSame(5300, $mode['gross_cents']);
        $this->assertSame(530, $mode['discount_cents']);
        $this->assertSame(4770, $mode['received_cents']);
        $this->cancel($order);
        $mode = $this->mode($this->report());
        $this->assertSame(4770, $mode['received_cents']);
        $this->assertSame(0, $mode['refunded_cents']);
        $this->assertSame(1, $mode['cancelled']);
    }

    public function test_closing_freezes_report_and_later_refund_belongs_to_new_shift(): void
    {
        $order = $this->waiterOrder();
        $payment = $this->pay($order, 'pix');
        $closed = $this->closeShift();
        $original = $closed->report_snapshot;
        $this->assertNotNull($original);
        $this->assertTrue($this->report()['frozen']);
        $next = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '0'], DiningContext::staff($this->cashier));
        $this->cancel($order);
        app(CashPayments::class)->refund($payment->id, ['operation_id' => (string) Str::uuid(), 'amount' => '53,00', 'method' => 'pix',
            'performed' => true, 'reason' => 'Devolução realizada no novo turno'], DiningContext::staff($this->manager));
        $this->assertEquals($original, $this->report()['data']);
        $this->assertSame(0, $this->mode($this->report())['cancelled']);
        $nextReport = $this->report($next->id);
        $this->assertSame(0, $this->mode($nextReport)['orders']);
        $this->assertSame(5300, $this->mode($nextReport)['refunded_cents']);
        $this->assertSame(-5300, $this->mode($nextReport)['net_cents']);
        $this->assertSame(-5300, $nextReport['data']['cash_totals']['pix']['expected']);
    }

    public function test_order_and_payment_on_different_shifts_are_not_reattributed(): void
    {
        $order = $this->waiterOrder();
        $this->closeShift();
        $next = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '0'], DiningContext::staff($this->cashier));
        $this->pay($order, 'card');
        $this->assertSame(1, $this->mode($this->report())['orders']);
        $this->assertSame(0, $this->mode($this->report())['payments']);
        $this->assertSame(0, $this->mode($this->report($next->id))['orders']);
        $this->assertSame(5300, $this->mode($this->report($next->id))['received_cents']);
        $this->assertSame($this->shift->id, $order->fresh()->cash_shift_id);
    }

    public function test_orders_without_shift_remain_explicit_and_are_not_moved_to_next_shift(): void
    {
        $this->closeShift();
        $order = $this->waiterOrder();
        $this->assertNull($order->cash_shift_id);
        $without = app(ShiftReports::class)->read(null, DiningContext::staff($this->manager));
        $this->assertSame(1, $this->mode($without)['orders']);
        $next = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '0'], DiningContext::staff($this->cashier));
        $this->assertSame(0, $this->mode($this->report($next->id))['orders']);
        $this->signIn($this->manager)->get(route('reports.unassigned'))->assertInertia(fn (Assert $p) => $p->where('unassignedCount', 1)->where('shift', null));
    }

    public function test_opening_cash_and_change_are_not_sales_and_closing_differences_are_preserved(): void
    {
        $order = $this->waiterOrder();
        $this->pay($order, 'cash');
        $this->assertSame(5300, $this->mode($this->report())['received_cents']);
        $this->assertSame(15300, $this->report()['data']['cash_totals']['cash']['expected']);
        $closed = $this->closeShift(['cash' => '150,00', 'pix' => '0', 'card' => '0'], ['reason' => 'Diferença conferida no teste']);
        $this->assertSame(-300, $closed->closing['cash']['difference']);
        $this->signIn($this->manager)->get(route('reports.show', $closed))->assertInertia(fn (Assert $p) => $p->where('frozen', true)->where('shift.rows.0.difference', 'R$ -3,00'));
    }

    public function test_delivery_fee_is_separate_and_contact_never_enters_report_snapshot(): void
    {
        $context = DiningContext::staff($this->manager);
        app(DeliveryCoverage::class)->save(null, ['name' => 'Área QA', 'city' => 'Cidade QA', 'state' => 'PI',
            'neighborhood' => 'Centro', 'fee' => '7,00', 'minimum' => '0', 'is_active' => true, 'reason' => 'Fixture'], $context);
        app(DeliveryCoverage::class)->service(true, 'Fixture aberta', $context);
        $input = ['phone' => '86999001122', 'address' => ['recipient' => 'Pessoa QA', 'street' => 'Rua Privada QA', 'number' => '10',
            'neighborhood' => 'Centro', 'city' => 'Cidade QA', 'state' => 'PI', 'cep' => '64000000'], 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]];
        $service = app(DeliveryOrders::class);
        $intent = $service->preview($input, DeliveryContext::staff($this->manager));
        $order = $service->confirm($intent->id, DeliveryContext::staff($this->manager));
        $courier = User::factory()->create(['role' => 'entregador']);
        $move = fn ($status, $actor) => app(OrderFulfillment::class)->transition($order->id, ['version' => $order->fresh()->version,
            'operation_id' => (string) Str::uuid(), 'status' => $status], DiningContext::staff($actor));
        $move('preparing', $this->manager);
        $move('ready', $this->manager);
        app(DeliveryDispatch::class)->assign($order->id, ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(),
            'courier_id' => $courier->id, 'reason' => 'Fixture'], $context);
        $move('waiting_courier', $this->manager);
        $move('dispatched', $courier);
        $pay = app(CashPayments::class);
        $intent = $pay->preview($order->delivery_account_id, [], DiningContext::staff($courier), 'delivery');
        $pay->confirm($order->delivery_account_id, $intent, ['method' => 'pix'], DiningContext::staff($courier), 'delivery');
        $report = $this->report();
        $delivery = $this->mode($report, 'delivery');
        $this->assertSame(5300, $delivery['gross_cents']);
        $this->assertSame(700, $delivery['fee_cents']);
        $this->assertSame(6000, $delivery['received_cents']);
        $this->assertStringNotContainsString('Rua Privada QA', json_encode($this->closeShift()->report_snapshot));
        $this->assertStringNotContainsString('999001122', json_encode($this->report()['data']));
    }

    public function test_legacy_authorship_and_closed_shift_without_snapshot_are_identified(): void
    {
        $order = $this->waiterOrder();
        DB::table('dining_orders')->where('id', $order->id)->update(['actor_role' => null]);
        $closed = $this->closeShift();
        DB::table('cash_shifts')->where('id', $closed->id)->update(['report_snapshot' => null]);
        $report = $this->report();
        $this->assertTrue($report['legacy']);
        $this->assertFalse($report['frozen']);
        $this->assertSame(1, $report['data']['unknown_authorship']);
        $this->assertSame([], $report['data']['waiters']);
    }

    public function test_only_manager_can_read_reports_in_http_and_application(): void
    {
        foreach (['caixa', 'garcom', 'cozinha', 'entregador'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->signIn($actor)->get(route('reports.show', $this->shift))->assertForbidden();
        }
        $this->signIn($this->manager)->get(route('reports.index'))->assertRedirect(route('reports.show', $this->shift));
        $this->expectException(AuthorizationException::class);
        app(ShiftReports::class)->read($this->shift->id, DiningContext::staff($this->waiter));
    }

    public function test_report_waiters_are_paginated_and_aggregation_uses_bounded_queries(): void
    {
        for ($i = 0; $i < 22; $i++) {
            $this->waiterOrder(User::factory()->create(['role' => 'garcom', 'name' => 'Garçom '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]));
        }
        $this->signIn($this->manager)->get(route('reports.show', $this->shift))->assertInertia(fn (Assert $p) => $p->has('waiters', 20)->where('waiterLinks.previous', null)->where('waiterLinks.next', fn ($value) => is_string($value)));
        $this->get(route('reports.show', ['shift' => $this->shift->id, 'waiters_page' => 2]))->assertInertia(fn (Assert $p) => $p->has('waiters', 2));
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ShiftReportData::class)->build($this->shift->id);
        $this->assertLessThanOrEqual(2, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_report_failure_rolls_back_shift_closure_without_partial_history(): void
    {
        $this->mock(ShiftReportData::class)->shouldReceive('build')->once()->andThrow(new \RuntimeException('Falha simulada no resumo'));
        try {
            $this->closeShift();
            $this->fail('Fechamento precisa do resumo persistido');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada no resumo', $exception->getMessage());
        }
        $this->assertNull($this->shift->fresh()->closed_at);
        $this->assertNull($this->shift->fresh()->report_snapshot);
        $this->assertDatabaseMissing('cash_events', ['action' => 'shift_closed']);
    }
}

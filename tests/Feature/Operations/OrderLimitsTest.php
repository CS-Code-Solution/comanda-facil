<?php

namespace Tests\Feature\Operations;

use App\Application\Cash\CashPayments;
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryCoverage;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CashTestCase;

class OrderLimitsTest extends CashTestCase
{
    public static function modalities(): array
    {
        return [['dining_room'], ['pickup'], ['delivery']];
    }

    #[DataProvider('modalities')]
    public function test_extreme_total_is_validation_failure_before_any_order_or_intent_is_saved(string $mode): void
    {
        $this->variant->forceFill(['price_cents' => 100000000])->save();
        $items = [['variant_id' => $this->variant->id, 'quantity' => 99]];
        $context = DiningContext::staff($this->manager);
        app(PickupOrders::class)->service(true, 'Preparar teste de limites', PickupContext::staff($this->manager));
        app(DeliveryCoverage::class)->save(null, ['name' => 'Área QA', 'city' => 'Cidade QA', 'state' => 'PI', 'neighborhood' => 'Centro',
            'fee' => '7,00', 'minimum' => '0', 'is_active' => true, 'reason' => 'Preparar teste de limites'], $context);
        app(DeliveryCoverage::class)->service(true, 'Preparar teste de limites', $context);
        try {
            match ($mode) {
                'dining_room' => app(DiningOrders::class)->preview($this->table->id, ['cycle' => $this->table->fresh()->cycle, 'items' => $items], $context),
                'pickup' => app(PickupOrders::class)->preview(['phone' => '86999001122', 'items' => $items], PickupContext::staff($this->manager)),
                'delivery' => app(DeliveryOrders::class)->preview(['phone' => '86999001122', 'items' => $items, 'address' => [
                    'recipient' => 'Pessoa QA', 'street' => 'Rua QA', 'number' => '10', 'neighborhood' => 'Centro', 'city' => 'Cidade QA', 'state' => 'PI', 'cep' => '64000000']], DeliveryContext::staff($this->manager)),
            };
            $this->fail('Valor excessivo deve gerar validação, sem chegar ao banco.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('basket', $exception->errors());
        }
        foreach (['dining_order_intents', 'pickup_intents', 'delivery_intents', 'dining_orders', 'print_jobs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_order_at_financial_boundary_can_be_confirmed_and_paid(): void
    {
        $this->variant->forceFill(['price_cents' => 100000000])->save();
        $order = $this->order();
        $context = DiningContext::staff($this->cashier);
        $payments = app(CashPayments::class);
        $intent = $payments->preview($order->session_id, [], $context);
        $payment = $payments->confirm($order->session_id, $intent, ['method' => 'pix'], $context);
        $this->assertSame(100000000, $order->total_cents);
        $this->assertSame($order->total_cents, $payment->amount_cents);
    }
}

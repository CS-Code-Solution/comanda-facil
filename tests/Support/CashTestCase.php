<?php

namespace Tests\Support;

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Models\CashPayment;
use App\Models\CashShift;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Support\Str;

abstract class CashTestCase extends DiningTestCase
{
    protected User $cashier;

    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cashier = User::factory()->create(['role' => 'caixa']);
        $this->shift = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100,00'], DiningContext::staff($this->cashier));
    }

    protected function order(array $extra = []): DiningOrder
    {
        $intent = $this->preview(DiningContext::staff($this->manager), $extra);

        return app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->manager));
    }

    protected function pay(DiningOrder $order, string $method = 'cash', ?User $actor = null, array $preview = []): CashPayment
    {
        $context = DiningContext::staff($actor ?? $this->cashier);
        $id = app(CashPayments::class)->preview($order->session_id, $preview, $context);

        return app(CashPayments::class)->confirm($order->session_id, $id, ['method' => $method, 'received' => $method === 'cash' ? '100,00' : null], $context);
    }
}

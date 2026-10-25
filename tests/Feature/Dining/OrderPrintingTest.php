<?php

namespace Tests\Feature\Dining;

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Orders\OrderFulfillment;
use App\Application\Orders\OrderPrinting;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiningTestCase;

class OrderPrintingTest extends DiningTestCase
{
    private DiningOrder $order;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();
        $intent = $this->preview();
        $this->order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        $this->job = DB::table('print_jobs')->where('target', 'cashier')->sole()->id;
    }

    public function test_operator_claims_then_explicitly_attests_printed_and_replay_has_no_duplicate_audit(): void
    {
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->act($this->job, 'claim', [], $context);
        $printing->act($this->job, 'claim', [], $context);
        $this->assertDatabaseHas('print_jobs', ['id' => $this->job, 'status' => 'printing']);
        $printing->act($this->job, 'printed', [], $context);
        $printing->act($this->job, 'printed', [], $context);
        $this->assertDatabaseHas('print_jobs', ['id' => $this->job, 'status' => 'printed']);
        $this->assertSame(3, DB::table('print_events')->where('job_id', $this->job)->count());
    }

    public function test_failure_requires_reason_and_reprint_has_new_copy_without_new_order(): void
    {
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->act($this->job, 'claim', [], $context);
        $this->signIn($this->manager)->post(route('printing.act', ['job' => $this->job, 'action' => 'failed']))->assertSessionHasErrors('reason');
        $printing->act($this->job, 'failed', ['reason' => 'Sem papel na impressora'], $context);
        $request = ['operation_id' => (string) Str::uuid(), 'reason' => 'Papel reposto, nova cópia'];
        $id = $printing->act($this->job, 'reprint', $request, $context);
        $this->assertSame($id, $printing->act($this->job, 'reprint', $request, $context));
        $this->assertDatabaseHas('print_jobs', ['id' => $id, 'copy' => 2, 'status' => 'pending', 'origin_job_id' => $this->job]);
        $this->assertDatabaseCount('dining_orders', 1);
        $this->assertDatabaseCount('print_jobs', 3);
        $this->assertDatabaseHas('print_events', ['job_id' => $id, 'action' => 'reprint', 'reason' => $request['reason'], 'actor_id' => $this->manager->id]);
        try {
            $printing->act($this->job, 'reprint', [...$request, 'operation_id' => (string) Str::uuid()], $context);
            $this->fail('An old tab cannot queue another copy while a newer one exists');
        } catch (ValidationException) {
            $this->assertDatabaseCount('print_jobs', 3);
        }
    }

    public function test_second_operator_cannot_claim_or_complete_active_print(): void
    {
        $printing = app(OrderPrinting::class);
        $printing->act($this->job, 'claim', [], DiningContext::staff($this->manager));
        $this->expectException(ValidationException::class);
        $printing->act($this->job, 'printed', [], DiningContext::staff(User::factory()->create(['role' => 'caixa'])));
    }

    public function test_interrupted_claim_can_be_recovered_after_two_minutes_with_reason(): void
    {
        $printing = app(OrderPrinting::class);
        $printing->act($this->job, 'claim', [], DiningContext::staff($this->manager));
        $this->travel(3)->minutes();
        $id = $printing->act($this->job, 'reprint', ['operation_id' => (string) Str::uuid(), 'reason' => 'Dispositivo desligou durante impressão'], DiningContext::staff(User::factory()->create(['role' => 'caixa'])));
        $this->assertDatabaseHas('print_jobs', ['id' => $this->job, 'status' => 'failed']);
        $this->assertDatabaseHas('print_jobs', ['id' => $id, 'status' => 'pending']);
    }

    public function test_change_supersedes_claim_and_prevents_stale_completion(): void
    {
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->act($this->job, 'claim', [], $context);
        app(OrderFulfillment::class)->transition($this->order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => 'Cliente cancelou antes do preparo'], $context);
        $this->assertDatabaseHas('print_jobs', ['id' => $this->job, 'status' => 'superseded']);
        $this->expectException(ValidationException::class);
        $printing->act($this->job, 'printed', [], $context);
    }

    public function test_printed_old_revision_remains_in_history_and_cannot_be_reprinted_as_current(): void
    {
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->act($this->job, 'claim', [], $context);
        $printing->act($this->job, 'printed', [], $context);
        app(OrderFulfillment::class)->transition($this->order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => 'Cliente cancelou o pedido'], $context);
        $this->signIn($this->manager)->get(route('printing.show', $this->job))->assertInertia(fn (Assert $page) => $page->where('job.stale', true)->where('job.canReprint', false));
        $this->expectException(ValidationException::class);
        $printing->act($this->job, 'reprint', ['operation_id' => (string) Str::uuid(), 'reason' => 'Tentativa de cópia antiga'], $context);
    }

    public function test_kitchen_cannot_print_cashier_ticket_and_waiter_cannot_access_spool(): void
    {
        $this->signIn($this->waiter)->get(route('printing.index'))->assertForbidden();
        $this->signIn(User::factory()->create(['role' => 'cozinha']))->get(route('printing.show', $this->job))->assertForbidden();
        $this->get(route('printing.index'))->assertInertia(fn (Assert $page) => $page->has('jobs', 1)->where('jobs.0.target', 'Cozinha'));
    }

    public function test_legacy_generation_is_explicit_idempotent_and_authorized(): void
    {
        DB::table('print_events')->delete();
        DB::table('print_jobs')->delete();
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->legacy($this->order->id, $context);
        $printing->legacy($this->order->id, $context);
        $this->assertDatabaseCount('print_jobs', 2);
        $this->expectException(AuthorizationException::class);
        $printing->legacy($this->order->id, DiningContext::staff($this->waiter));
    }
}

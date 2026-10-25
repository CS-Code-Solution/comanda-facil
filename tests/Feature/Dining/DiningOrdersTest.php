<?php

namespace Tests\Feature\Dining;

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\GuestGrant;
use App\Application\Dining\ManageDining;
use App\Models\DiningSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\DiningTestCase;

class DiningOrdersTest extends DiningTestCase
{
    public function test_preview_does_not_open_session_and_confirmation_is_idempotent(): void
    {
        $ctx = DiningContext::guest($this->grant());
        $intent = $this->preview($ctx);
        $this->assertDatabaseCount('dining_sessions', 0);
        $orders = app(DiningOrders::class);
        $first = $orders->confirm($intent->id, $this->table->id, $ctx);
        $second = $orders->confirm($intent->id, $this->table->id, $ctx);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('dining_orders', 1);
        $this->assertDatabaseCount('dining_order_items', 1);
        $this->assertDatabaseCount('dining_sessions', 1);
        $this->assertSame('confirmed', $first->status);
        $this->assertSame('dining_room', $first->modality);
        $this->assertSame(5300, $first->total_cents);
        $this->assertNull($first->actor_id);
        $this->assertNotNull($first->client_hash);
        $this->assertSame(1, DB::table('dining_events')->where('action', 'order_confirmed')->count());
    }

    public function test_two_waiters_share_session_and_keep_their_real_authorship(): void
    {
        $other = User::factory()->create(['role' => 'garcom']);
        $orders = app(DiningOrders::class);
        $first = $orders->confirm($this->preview()->id, $this->table->id, DiningContext::staff($this->waiter));
        $second = $orders->confirm($this->preview(DiningContext::staff($other))->id, $this->table->id, DiningContext::staff($other));
        $this->assertSame($first->session_id, $second->session_id);
        $this->assertSame($this->waiter->id, $first->actor_id);
        $this->assertSame($other->id, $second->actor_id);
        $this->assertNull($first->client_hash);
    }

    public function test_catalog_edits_after_confirmation_do_not_change_order_snapshot(): void
    {
        $ctx = DiningContext::staff($this->waiter);
        $order = app(DiningOrders::class)->confirm($this->preview($ctx)->id, $this->table->id, $ctx);
        $this->variant->forceFill(['price_cents' => 9999, 'name' => 'Novo nome'])->save();
        $snapshot = $order->items()->firstOrFail()->snapshot;
        $this->assertSame(5300, $snapshot['total_cents']);
        $this->assertSame('Grande', $snapshot['variant_name']);
        $this->assertSame('size', $snapshot['unit']);
        $this->assertSame('g', $snapshot['pizza_size']);
        $this->assertNull($snapshot['measure_value']);
        $this->assertSame('Sem observação adicional', $snapshot['note']);
    }

    public function test_price_change_between_preview_and_confirmation_requires_new_review(): void
    {
        $intent = $this->preview();
        $this->variant->forceFill(['price_cents' => 5400])->save();
        try {
            app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
            $this->fail('Não deve confirmar preço alterado sem nova prévia.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('basket', $error->errors());
            $this->assertDatabaseCount('dining_orders', 0);
            $this->assertDatabaseCount('dining_sessions', 0);
        }
    }

    public function test_unavailability_after_preview_rolls_back_entire_order(): void
    {
        $intent = $this->preview();
        $this->variant->forceFill(['is_available' => false])->save();
        $this->expectException(ValidationException::class);
        try {
            app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        } finally {
            $this->assertDatabaseCount('dining_orders', 0);
            $this->assertDatabaseCount('dining_sessions', 0);
        }
    }

    public function test_expired_intent_is_rejected_but_consumed_intent_replays_original(): void
    {
        $ctx = DiningContext::staff($this->waiter);
        $used = $this->preview($ctx);
        $order = app(DiningOrders::class)->confirm($used->id, $this->table->id, $ctx);
        $unused = $this->preview($ctx);
        $this->assertSame(now()->addMinutes(10)->timestamp, $unused->fresh()->expires_at->timestamp);
        $this->travel(11)->minutes();
        $this->assertTrue($unused->fresh()->expires_at < now(), $unused->fresh()->expires_at->toIso8601String().' / '.now()->toIso8601String());
        $this->assertSame($order->id, app(DiningOrders::class)->confirm($used->id, $this->table->id, $ctx)->id);
        $this->expectException(ValidationException::class);
        app(DiningOrders::class)->confirm($unused->id, $this->table->id, $ctx);
    }

    public function test_foreign_browser_cannot_consume_intent(): void
    {
        $intent = $this->preview(DiningContext::guest($this->grant()));
        $this->expectException(ModelNotFoundException::class);
        app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::guest($this->grant()));
    }

    public function test_public_context_cannot_apply_manual_discount(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->preview(DiningContext::guest($this->grant()), ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'manual_discount' => ['type' => 'fixed', 'value' => '1,00']]]]);
    }

    public function test_closed_cycle_reopens_with_alert_and_old_form_cannot_open_new_account(): void
    {
        $oldGrant = $this->grant();
        $ctx = DiningContext::guest($oldGrant);
        $intent = $this->preview($ctx);
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, $ctx);
        // Fixture representa a futura confirmação de pagamento, sem endpoint de bypass.
        $session = DiningSession::query()->findOrFail($order->session_id);
        $session->forceFill(['settled_at' => now()])->save();
        app(ManageDining::class)->close($this->table->id, $session->id, DiningContext::staff($this->waiter));
        $this->assertSame($order->id, app(DiningOrders::class)->confirm($intent->id, $this->table->id, $ctx)->id);
        $this->assertDatabaseCount('dining_sessions', 1);
        $newContext = DiningContext::guest($this->grant());
        $newOrder = app(DiningOrders::class)->confirm($this->preview($newContext)->id, $this->table->id, $newContext);
        $this->assertNotSame($order->session_id, $newOrder->session_id);
        $this->assertDatabaseCount('dining_alerts', 1);
        $this->assertSame(1, DiningSession::query()->whereNull('closed_at')->count());
        $this->expectException(AuthorizationException::class);
        $this->preview($ctx);
    }

    public function test_unpaid_positive_account_cannot_close_or_be_spoofed_paid_from_http(): void
    {
        $order = app(DiningOrders::class)->confirm($this->preview()->id, $this->table->id, DiningContext::staff($this->waiter));
        $this->signIn($this->waiter)->post(route('dining.close', ['table' => $this->table->id, 'sessionId' => $order->session_id]), ['settled_at' => now()->toIso8601String()])->assertSessionHasErrors('close');
        $this->assertNull(DiningSession::query()->findOrFail($order->session_id)->closed_at);
        $this->assertNull(DiningSession::query()->findOrFail($order->session_id)->settled_at);
    }

    public function test_zero_total_account_can_close_and_paid_account_cannot_receive_more_orders(): void
    {
        $ctx = DiningContext::staff($this->manager);
        $intent = $this->preview($ctx, ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'manual_discount' => ['type' => 'percentage', 'value' => '100']]]]);
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, $ctx);
        app(ManageDining::class)->close($this->table->id, $order->session_id, DiningContext::staff($this->waiter));
        $this->assertNotNull($order->session->closed_at);
        $other = app(DiningOrders::class)->confirm($this->preview()->id, $this->table->id, DiningContext::staff($this->waiter));
        $other->session->forceFill(['settled_at' => now()])->save();
        $this->expectException(ValidationException::class);
        $this->preview();
    }

    public function test_public_order_burst_is_limited_even_without_http_middleware(): void
    {
        $ctx = DiningContext::guest($this->grant());
        for ($i = 0; $i < 3; $i++) {
            app(DiningOrders::class)->confirm($this->preview($ctx)->id, $this->table->id, $ctx);
        }
        $this->expectException(ValidationException::class);
        try {
            app(DiningOrders::class)->confirm($this->preview($ctx)->id, $this->table->id, $ctx);
        } finally {
            $this->assertDatabaseCount('dining_orders', 3);
        }
    }

    public function test_revoked_staff_context_cannot_confirm(): void
    {
        $ctx = DiningContext::staff($this->waiter);
        $intent = $this->preview($ctx);
        $this->waiter->forceFill(['auth_version' => 2])->save();
        $this->expectException(AuthorizationException::class);
        app(DiningOrders::class)->confirm($intent->id, $this->table->id, $ctx);
    }

    public function test_expired_guest_grant_is_rejected(): void
    {
        $grant = $this->grant();
        $expired = new GuestGrant($grant->tableId, $grant->version, $grant->cycle, $grant->visitor, now()->timestamp - 1);
        $this->expectException(AuthorizationException::class);
        $this->preview(DiningContext::guest($expired));
    }

    public function test_a_bad_second_item_does_not_persist_an_intent_or_partial_order(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->preview(extra: ['items' => [['variant_id' => $this->variant->id, 'quantity' => 1], ['variant_id' => 999999, 'quantity' => 1]]]);
        } finally {
            $this->assertDatabaseCount('dining_order_intents', 0);
            $this->assertDatabaseCount('dining_order_items', 0);
        }
    }

    public function test_pause_after_preview_prevents_confirmation_without_consuming_intent(): void
    {
        $intent = $this->preview();
        app(ManageDining::class)->service(false, 'Pausa após prévia', DiningContext::staff($this->manager));
        $this->expectException(ValidationException::class);
        try {
            app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        } finally {
            $this->assertDatabaseCount('dining_orders', 0);
        }
    }

    public function test_postgresql_rejects_second_open_session(): void
    {
        $order = app(DiningOrders::class)->confirm($this->preview()->id, $this->table->id, DiningContext::staff($this->waiter));
        try {
            DB::transaction(fn () => DB::table('dining_sessions')->insert(['table_id' => $this->table->id, 'cycle' => 1, 'opened_at' => now()]));
            $this->fail('Deveria impedir duas sessões abertas.');
        } catch (QueryException $error) {
            $this->assertSame('23505', $error->errorInfo[0]);
        }
        $this->assertDatabaseHas('dining_sessions', ['id' => $order->session_id, 'closed_at' => null]);
    }
}

<?php

namespace Tests\Feature\Dining;

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Orders\OperationsChanges;
use App\Application\Orders\OrderFulfillment;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiningTestCase;

class OperationsRealtimeTest extends DiningTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app']);
    }

    private function publicOrder(): array
    {
        $grant = $this->grant();
        $intent = $this->preview(DiningContext::guest($grant));
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::guest($grant));
        $this->withSession(['dining.grants.'.$this->table->id => ['visitor' => $grant->visitor, 'version' => $grant->version,
            'cycle' => $grant->cycle, 'expires' => $grant->expiresAt]]);

        return [$order, $grant];
    }

    public function test_guest_channel_requires_own_browser_grant_and_exact_private_order(): void
    {
        [$order] = $this->publicOrder();
        $url = route('qr.realtime', ['token' => $this->table->qr_token, 'reference' => $order->public_id]);
        $input = ['channel_name' => 'private-dining.order.'.$order->public_id, 'socket_id' => '123.456'];
        $this->postJson($url, $input)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson($url, [...$input, 'channel_name' => 'private-operations.kitchen'])->assertForbidden();
        $this->withSession(['dining.grants.'.$this->table->id => null])->postJson($url, $input)->assertForbidden();
    }

    public function test_another_visitor_cannot_subscribe_by_knowing_order_reference(): void
    {
        [$order] = $this->publicOrder();
        $other = $this->grant();
        $this->withSession(['dining.grants.'.$this->table->id => ['visitor' => $other->visitor, 'version' => $other->version,
            'cycle' => $other->cycle, 'expires' => $other->expiresAt]])->postJson(route('qr.realtime', ['token' => $this->table->qr_token, 'reference' => $order->public_id]),
                ['channel_name' => 'private-dining.order.'.$order->public_id, 'socket_id' => '123.456'])->assertNotFound();
    }

    public function test_expired_grant_cannot_reauthorize_after_reconnection(): void
    {
        [$order] = $this->publicOrder();
        $this->travel(121)->minutes();
        $this->postJson(route('qr.realtime', ['token' => $this->table->qr_token, 'reference' => $order->public_id]),
            ['channel_name' => 'private-dining.order.'.$order->public_id, 'socket_id' => '123.456'])->assertForbidden();
    }

    public function test_staff_channels_obey_role_and_revocation(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::purge();
        require base_path('routes/channels.php');
        $kitchen = User::factory()->create(['role' => 'cozinha']);
        $input = ['channel_name' => 'private-operations.kitchen', 'socket_id' => '123.456'];
        $this->signIn($kitchen)->postJson('/broadcasting/auth', $input)->assertOk();
        $this->postJson('/broadcasting/auth', [...$input, 'channel_name' => 'private-operations.dining'])->assertForbidden();
        $this->postJson('/broadcasting/auth', [...$input, 'channel_name' => 'private-dining.order.'.(string) Str::uuid()])->assertForbidden();
        $kitchen->forceFill(['auth_version' => $kitchen->auth_version + 1])->save();
        $this->postJson('/broadcasting/auth', $input)->assertUnauthorized();
    }

    public function test_failed_broadcast_keeps_outbox_for_retry_without_recreating_order(): void
    {
        $intent = $this->preview();
        app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        $connection = \Mockery::mock(Broadcaster::class);
        $connection->shouldReceive('broadcast')->once()->andThrow(new \RuntimeException('sensitive URL must not persist'));
        Broadcast::shouldReceive('connection')->with('reverb')->once()->andReturn($connection);
        $this->assertSame(0, app(OperationsChanges::class)->publish());
        $event = DB::table('operations_outbox')->sole();
        $this->assertNull($event->delivered_at);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('broadcast_unavailable', $event->last_error);
        $this->assertSame(0, app(OperationsChanges::class)->publish());
        $this->assertDatabaseCount('dining_orders', 1);
    }

    public function test_outbox_publishes_only_minimal_invalidation_and_marks_delivery(): void
    {
        $intent = $this->preview();
        $order = app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        $connection = \Mockery::mock(Broadcaster::class);
        $connection->shouldReceive('broadcast')->once()->with(\Mockery::on(fn ($channels) => in_array('private-dining.order.'.$order->public_id, $channels, true)),
            'operations.changed', \Mockery::on(fn ($data) => array_keys($data) === ['id']));
        Broadcast::shouldReceive('connection')->with('reverb')->once()->andReturn($connection);
        $this->assertSame(1, app(OperationsChanges::class)->publish());
        $this->assertNotNull(DB::table('operations_outbox')->sole()->delivered_at);
        $this->assertSame(0, app(OperationsChanges::class)->publish());
    }

    public function test_rolled_back_order_leaves_no_ticket_history_or_event(): void
    {
        DB::beginTransaction();
        $intent = $this->preview();
        app(DiningOrders::class)->confirm($intent->id, $this->table->id, DiningContext::staff($this->waiter));
        DB::rollBack();
        $this->assertDatabaseCount('dining_orders', 0);
        $this->assertDatabaseCount('operations_outbox', 0);
        $this->assertDatabaseCount('print_jobs', 0);
        $this->assertDatabaseCount('order_history', 0);
    }

    public function test_public_tracking_does_not_expose_staff_identity_internal_reason_or_history(): void
    {
        [$order] = $this->publicOrder();
        app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1,
            'status' => 'cancelled', 'reason' => 'Motivo interno restrito'], DiningContext::staff($this->manager));
        $response = $this->get(route('qr.receipt', ['token' => $this->table->qr_token, 'reference' => $order->public_id]));
        $response->assertInertia(fn (Assert $page) => $page->where('order.status', 'Cancelado')->where('order.total', 'R$ 0,00')->where('auth.user', null)->has('order.timeline', 2)->missing('history')->missing('order.actor_id'));
        $response->assertDontSee('Motivo interno restrito');
    }
}

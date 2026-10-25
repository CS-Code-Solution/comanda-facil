<?php

namespace Tests\Feature\Dining;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\ManageCatalog;
use App\Application\Dining\DiningCalls;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Enums\CatalogResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiningTestCase;

class DiningAccessTest extends DiningTestCase
{
    public function test_guest_get_opens_menu_but_does_not_open_attendance_or_expose_staff(): void
    {
        $this->signIn($this->manager)->get(route('qr.menu', $this->table->qr_token))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $page) => $page
            ->component('public/dining/menu')->where('auth.user', null)->has('navigation', 0)->where('table.number', 7)->has('products', 8));
        $this->assertDatabaseCount('dining_sessions', 0);
        $this->assertDatabaseCount('dining_orders', 0);
    }

    public function test_public_preview_and_confirm_use_guest_identity_even_when_staff_is_logged_in(): void
    {
        $this->signIn($this->manager);
        $token = $this->table->qr_token;
        $this->get(route('qr.menu', $token));
        $response = $this->post(route('qr.preview', $token), ['cycle' => 0, 'actor_id' => $this->manager->id, 'total_cents' => 1, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]])->assertRedirect();
        $intent = DB::table('dining_order_intents')->sole();
        $this->assertNull($intent->actor_id);
        $this->assertSame('qr', $intent->source);
        $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page) => $page->component('public/dining/review')->where('total', 'R$ 106,00')->where('auth.user', null));
        $response = $this->post(route('qr.confirm', ['token' => $token, 'intent' => $intent->id]), ['total_cents' => 1])->assertRedirect();
        $this->get($response->headers->get('Location'))->assertInertia(fn (Assert $page) => $page->component('public/dining/receipt')->where('order.total', 'R$ 106,00')->where('auth.user', null)->missing('order.actor_id')->missing('order.client_hash'));
    }

    public function test_guest_cannot_use_another_tables_grant_or_read_other_visitors_receipt(): void
    {
        $ctx = DiningContext::guest($this->grant());
        $order = app(DiningOrders::class)->confirm($this->preview($ctx)->id, $this->table->id, $ctx);
        $token = $this->table->qr_token;
        $this->get(route('qr.menu', $token));
        $this->get(route('qr.receipt', ['token' => $token, 'reference' => $order->public_id]))->assertNotFound();
        $second = app(ManageDining::class)->createTable(['number' => 8], DiningContext::staff($this->manager));
        $this->post(route('qr.preview', $second->qr_token), ['cycle' => 0, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]])->assertForbidden();
    }

    public function test_block_and_service_pause_prevent_public_orders(): void
    {
        app(ManageDining::class)->service(false, 'Pausa teste', DiningContext::staff($this->manager));
        $this->get(route('qr.menu', $this->table->qr_token))->assertInertia(fn (Assert $page) => $page->where('receiving', false));
        $this->post(route('qr.preview', $this->table->qr_token), ['cycle' => 0, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]])->assertSessionHasErrors('basket');
        $this->assertDatabaseCount('dining_order_intents', 0);
    }

    public function test_rotating_qr_invalidates_old_link_and_guest_context_and_keeps_token_out_of_audit(): void
    {
        $grant = $this->grant();
        $oldToken = $this->table->qr_token;
        $this->assertNotSame($oldToken, DB::table('dining_tables')->where('id', $this->table->id)->value('qr_token'));
        app(ManageDining::class)->changeTable($this->table->id, ['is_active' => true, 'is_blocked' => false, 'reason' => 'Troca teste'], DiningContext::staff($this->manager), true);
        $this->get(route('qr.menu', $oldToken))->assertNotFound();
        $this->assertStringNotContainsString($oldToken, DB::table('dining_events')->pluck('details')->implode(' '));
        $this->assertArrayNotHasKey('qr_token', $this->table->toArray());
        $this->expectException(AuthorizationException::class);
        $this->preview(DiningContext::guest($grant));
    }

    public function test_staff_permissions_and_qr_image_are_protected(): void
    {
        foreach (['gestor' => true, 'garcom' => true, 'caixa' => true, 'cozinha' => false, 'entregador' => false] as $role => $canEnter) {
            $this->signIn(User::factory()->create(['role' => $role]));
            $response = $this->get(route('dining.index'));
            $canEnter ? $response->assertOk() : $response->assertForbidden();
            if ($role !== 'gestor') {
                $this->post(route('dining.store'), ['number' => 99])->assertForbidden();
                $this->get(route('dining.qr.image', $this->table))->assertForbidden();
            }
        }
        $this->signIn($this->manager)->get(route('dining.qr.image', $this->table))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get(route('dining.qr', $this->table))->assertInertia(fn (Assert $page) => $page->component('dining/qr')->where('number', 7));
        $this->signIn(User::factory()->create(['role' => 'caixa']))->get(route('dining.menu', $this->table))->assertForbidden();
    }

    public function test_calls_are_coalesced_and_only_claimant_completes_them(): void
    {
        $service = app(DiningCalls::class);
        $grant = $this->grant();
        $call = $service->request($grant);
        $this->assertSame($call->id, $service->request($this->grant())->id);
        $this->assertDatabaseCount('dining_sessions', 0);
        $service->respond($call->id, false, DiningContext::staff($this->waiter));
        try {
            $service->respond($call->id, true, DiningContext::staff($this->manager));
            $this->fail('Outro atendente não deve concluir o chamado.');
        } catch (ValidationException) {
            $this->assertSame('claimed', $call->fresh()->status);
        }
        $service->respond($call->id, true, DiningContext::staff($this->waiter));
        $this->assertSame('completed', $call->fresh()->status);
        $this->expectException(ValidationException::class);
        $service->request($grant);
    }

    public function test_public_write_rate_limit_and_call_deduplication(): void
    {
        $token = $this->table->qr_token;
        $this->get(route('qr.menu', $token));
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('qr.call', $token))->assertRedirect();
        }
        $this->post(route('qr.call', $token))->assertStatus(429);
        $this->assertDatabaseCount('dining_calls', 1);
    }

    public function test_public_catalog_context_never_allows_catalog_writes(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ManageCatalog::class)->save(CatalogResource::Categories, ['name' => 'Proibido'], CatalogContext::dining($this->grant()));
    }

    public function test_duplicate_table_number_and_missing_management_reason_are_rejected(): void
    {
        $this->signIn($this->manager)->post(route('dining.store'), ['number' => 7])->assertSessionHasErrors('number');
        $this->put(route('dining.update', $this->table), ['is_active' => true, 'is_blocked' => true])->assertSessionHasErrors('reason');
        $this->assertFalse($this->table->fresh()->is_blocked);
    }

    public function test_revoked_user_cannot_modify_dining_directly(): void
    {
        $context = DiningContext::staff($this->manager);
        $this->manager->forceFill(['is_active' => false])->save();
        $this->expectException(AuthorizationException::class);
        app(ManageDining::class)->service(false, 'Tentativa inválida', $context);
    }

    public function test_public_photo_needs_table_grant_and_hides_unpublished_product(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('produto.jpg')->store('catalog/photos', 'local');
        $product = $this->variant->product;
        $product->forceFill(['photo_path' => $path, 'is_available' => false])->save();
        $url = route('qr.photo', ['token' => $this->table->qr_token, 'product' => $product->id]);
        $this->get($url)->assertForbidden();
        $this->get(route('qr.menu', $this->table->qr_token));
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $product->forceFill(['is_active' => false])->save();
        $this->get($url)->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('public/dining/problem')->where('auth.user', null));
    }
}

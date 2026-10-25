<?php

namespace Tests\Feature\Catalog;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\CatalogLock;
use App\Application\Catalog\ManageCatalog;
use App\Enums\CatalogResource as Resource;
use App\Enums\StaffRole;
use App\Models\CatalogCategory;
use App\Models\CatalogEvent;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'gestor']);
    }

    private function save(Resource $resource, array $data, ?int $id = null): Model
    {
        return app(ManageCatalog::class)->save($resource, $data, CatalogContext::web($this->manager), $id);
    }

    private function import(bool $publish = true): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => $publish]);
    }

    public function test_import_is_draft_by_default_and_does_not_overwrite_operator_changes(): void
    {
        $this->import(false);
        $this->assertDatabaseCount('catalog_categories', 6);
        $this->assertDatabaseCount('catalog_products', 8);
        $this->assertDatabaseCount('catalog_variants', 22);
        $this->assertDatabaseCount('catalog_options', 2);
        $this->assertSame(0, CatalogProduct::query()->where('is_active', true)->count());
        $this->assertSame(0, CatalogProduct::query()->whereNotNull('photo_path')->count());
        $variant = CatalogVariant::query()->firstOrFail();
        $variant->forceFill(['price_cents' => 9999])->save();
        $revision = CatalogLock::revision();
        $this->import(true);
        $this->assertDatabaseCount('catalog_products', 8);
        $this->assertDatabaseCount('catalog_option_groups', 1);
        $this->assertSame(9999, $variant->fresh()->price_cents);
        $this->assertSame($revision, CatalogLock::revision());
        $this->assertSame(0, CatalogProduct::query()->where('is_active', true)->count());
    }

    public function test_every_staff_role_can_consult_but_only_manager_can_edit_or_discount(): void
    {
        $this->import();
        $product = CatalogProduct::query()->where('name', 'Calabresa')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        foreach (StaffRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $this->signIn($user)->get(route('catalog.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('catalog/index')->has('products', 8));
            $this->get(route('catalog.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page->component('catalog/show')->where('canManualDiscount', $role === StaffRole::Manager));
            $response = $this->get(route('catalog.admin.index', Resource::Products->value));
            $role === StaffRole::Manager ? $response->assertOk() : $response->assertForbidden();
            if ($role !== StaffRole::Manager) {
                $this->post(route('catalog.admin.store', Resource::Categories->value), ['name' => 'Invasão'])->assertForbidden();
                $this->put(route('catalog.admin.update', ['resource' => Resource::Products->value, 'id' => $product->id]), [])->assertForbidden();
                $this->post(route('catalog.quote', $product), ['variant_id' => $variant->id, 'quantity' => 1, 'manual_discount' => ['type' => 'fixed', 'value' => '1,00']])->assertForbidden();
            }
        }
        $this->assertDatabaseMissing('catalog_categories', ['name' => 'Invasão']);
    }

    public function test_manager_can_open_all_forms_and_edit_each_imported_resource(): void
    {
        $this->import();
        $promotion = $this->save(Resource::Promotions, ['name' => 'Teste de formulário', 'scope' => 'all', 'discount_type' => 'fixed', 'discount_value' => '1,00', 'starts_at' => '2026-10-24T10:00', 'ends_at' => '2026-10-24T11:00']);
        $this->signIn($this->manager);
        foreach (Resource::cases() as $resource) {
            $class = $resource->model();
            $record = $class::query()->firstOrFail();
            $this->get(route('catalog.admin.index', $resource->value))->assertOk();
            $this->get(route('catalog.admin.create', $resource->value))->assertOk()->assertInertia(fn (Assert $page) => $page->component('catalog/admin/form')->where('editing', false)->has('fields'));
            $this->get(route('catalog.admin.edit', ['resource' => $resource->value, 'id' => $record->getKey()]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('editing', true)->has('fields')->has('events', 1));
        }
        $this->assertSame('Teste de formulário', $promotion->getAttribute('name'));
        $this->get('/admin/catalogo/users')->assertNotFound();
    }

    public function test_catalog_pages_do_not_lazy_load_relationships(): void
    {
        $this->import();
        Model::preventLazyLoading();
        try {
            $product = CatalogProduct::query()->where('name', 'Calabresa')->firstOrFail();
            $this->signIn($this->manager)->get(route('catalog.index'))->assertOk();
            $this->get(route('catalog.show', $product))->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_malformed_product_identifier_returns_not_found(): void
    {
        $this->signIn($this->manager)->get('/catalogo/invalido')->assertNotFound();
        $this->post('/catalogo/invalido/cotacao', ['variant_id' => 1, 'quantity' => 1])->assertNotFound();
    }

    public function test_web_creation_price_update_and_audit_are_atomic(): void
    {
        $this->signIn($this->manager)->post(route('catalog.admin.store', Resource::Categories->value), ['name' => 'Teste', 'is_active' => true])->assertRedirect();
        $category = CatalogCategory::query()->firstOrFail();
        $this->post(route('catalog.admin.store', Resource::Products->value), ['name' => 'Produto teste', 'category_id' => $category->id, 'type' => 'simple', 'is_reviewed' => true, 'is_active' => true, 'is_available' => true, 'photo_path' => '../invalid'])->assertRedirect();
        $product = CatalogProduct::query()->firstOrFail();
        $this->assertNull($product->photo_path);
        $input = ['name' => 'Uma pessoa', 'product_id' => $product->id, 'unit' => 'people', 'measure_value' => 1, 'price' => '19,90', 'is_reviewed' => true, 'is_active' => true, 'is_available' => true];
        $this->post(route('catalog.admin.store', Resource::Variants->value), $input)->assertRedirect();
        $variant = CatalogVariant::query()->firstOrFail();
        $this->put(route('catalog.admin.update', ['resource' => Resource::Variants->value, 'id' => $variant->id]), [...$input, 'price' => '21,50'])->assertRedirect();
        $event = CatalogEvent::query()->latest('id')->firstOrFail();
        $this->assertSame(1990, $event->before['price_cents']);
        $this->assertSame(2150, $event->after['price_cents']);
        $this->assertSame($this->manager->id, $event->actor_id);
        $this->assertSame(4, CatalogLock::revision());
        $this->put(route('catalog.admin.update', ['resource' => Resource::Variants->value, 'id' => $variant->id]), [...$input, 'price' => '-1'])->assertSessionHasErrors('price');
        $this->assertSame(2150, $variant->fresh()->price_cents);
        $this->assertDatabaseCount('catalog_events', 4);
        $this->assertSame(4, CatalogLock::revision());
    }

    public function test_unknown_option_price_cannot_be_published_or_assumed_zero(): void
    {
        $group = $this->save(Resource::Groups, ['name' => 'Bordas pendentes', 'min_selections' => 0, 'max_selections' => 1, 'is_active' => true]);
        $input = ['group_id' => $group->getKey(), 'name' => 'Borda sem preço', 'max_quantity' => 1];
        $option = $this->save(Resource::Options, $input);
        $this->assertNull($option->getAttribute('price_cents'));
        $this->assertFalse($option->getAttribute('is_available'));
        $this->expectException(ValidationException::class);
        $this->save(Resource::Options, [...$input, 'is_available' => true, 'is_reviewed' => true], $option->getKey());
    }

    public function test_unreviewed_product_cannot_be_published(): void
    {
        $category = $this->save(Resource::Categories, ['name' => 'Teste']);
        $this->expectException(ValidationException::class);
        $this->save(Resource::Products, ['name' => 'Sem revisão', 'category_id' => $category->getKey(), 'type' => 'simple', 'is_active' => true]);
    }

    public function test_combo_piece_count_must_match_fixed_composition(): void
    {
        $this->import();
        $combo = CatalogProduct::query()->where('type', 'combo')->firstOrFail();
        $this->expectException(ValidationException::class);
        $this->save(Resource::Variants, ['product_id' => $combo->id, 'name' => 'Quantidade errada', 'unit' => 'pieces', 'measure_value' => 9, 'price' => '27,00']);
    }

    public function test_product_type_and_variant_parent_cannot_be_changed_after_creation(): void
    {
        $this->import();
        $pizza = CatalogProduct::query()->where('name', 'Calabresa')->firstOrFail();
        $this->expectException(ValidationException::class);
        $this->save(Resource::Products, ['category_id' => $pizza->category_id, 'name' => $pizza->name, 'type' => 'simple'], $pizza->id);
    }

    public function test_invalid_promotion_scope_and_percent_are_rejected(): void
    {
        $this->signIn($this->manager);
        $input = ['name' => 'Inválida', 'scope' => 'category', 'discount_type' => 'percentage', 'discount_value' => '101', 'starts_at' => '2026-10-24T12:00', 'ends_at' => '2026-10-24T11:00'];
        $this->post(route('catalog.admin.store', Resource::Promotions->value), $input)->assertSessionHasErrors('discount_value');
        $this->post(route('catalog.admin.store', Resource::Promotions->value), [...$input, 'discount_value' => '10'])->assertSessionHasErrors('ends_at');
        $this->post(route('catalog.admin.store', Resource::Promotions->value), [...$input, 'discount_value' => '10', 'ends_at' => '2026-10-24T13:00'])->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('catalog_promotions', 0);
    }

    public function test_promotion_roundtrip_preserves_restaurant_time_with_utc_connection(): void
    {
        $promotion = $this->save(Resource::Promotions, ['name' => 'Fuso horário', 'scope' => 'all', 'discount_type' => 'fixed', 'discount_value' => '1,00', 'starts_at' => '2026-10-24T12:00', 'ends_at' => '2026-10-24T13:00']);
        $this->assertSame('2026-10-24T15:00:00+00:00', $promotion->fresh()->getAttribute('starts_at')->toIso8601String());
        $this->signIn($this->manager)->get(route('catalog.admin.edit', ['resource' => Resource::Promotions->value, 'id' => $promotion->getKey()]))->assertInertia(fn (Assert $page) => $page->where('fields.7.value', '2026-10-24T12:00'));
    }

    public function test_draft_catalog_is_hidden_and_quote_rejects_foreign_variant(): void
    {
        $this->import(false);
        $product = CatalogProduct::query()->firstOrFail();
        $this->signIn($this->manager)->get(route('catalog.index'))->assertInertia(fn (Assert $page) => $page->has('products', 0));
        $this->get(route('catalog.show', $product))->assertNotFound();
        $this->post(route('catalog.quote', $product), ['variant_id' => CatalogVariant::query()->where('product_id', '!=', $product->id)->firstOrFail()->id, 'quantity' => 1])->assertSessionHasErrors('variant_id');
    }

    public function test_quote_post_redirects_to_get_with_server_total_and_has_no_catalog_write(): void
    {
        $this->import();
        $product = CatalogProduct::query()->where('name', 'Calabresa')->firstOrFail();
        $variant = $product->variants()->where('pizza_size', 'p')->firstOrFail();
        $revision = CatalogLock::revision();
        $this->signIn($this->manager)->post(route('catalog.quote', $product), ['variant_id' => $variant->id, 'quantity' => 2, 'total_cents' => 1])->assertRedirect(route('catalog.show', $product))->assertSessionHas('catalog_quote.total_cents', 6400);
        $this->get(route('catalog.show', $product))->assertInertia(fn (Assert $page) => $page->where('quote.total_cents', 6400)->where('quote.total_label', 'R$ 64,00'));
        $this->assertSame($revision, CatalogLock::revision());
    }

    public function test_quote_from_another_tab_is_not_shown_for_a_different_product(): void
    {
        $this->import();
        $first = CatalogProduct::query()->where('name', 'Calabresa')->firstOrFail();
        $other = CatalogProduct::query()->where('name', 'Laranja')->firstOrFail();
        $this->signIn($this->manager)->post(route('catalog.quote', $first), ['variant_id' => $first->variants()->firstOrFail()->id, 'quantity' => 1])->assertRedirect();
        $this->get(route('catalog.show', $other))->assertInertia(fn (Assert $page) => $page->where('quote', null));
    }

    public function test_direct_use_case_rejects_demoted_actor_and_preserves_database(): void
    {
        $ctx = CatalogContext::web($this->manager);
        $this->manager->forceFill(['role' => 'garcom'])->save();
        try {
            app(ManageCatalog::class)->save(Resource::Categories, ['name' => 'Proibido'], $ctx);
            $this->fail('Deveria negar ator sem permissão.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('catalog_categories', 0);
            $this->assertDatabaseCount('catalog_events', 0);
        }
    }

    public function test_postgresql_constraints_protect_price_review_and_scope(): void
    {
        $this->import();
        $variant = CatalogVariant::query()->firstOrFail();
        $attempts = [
            fn () => DB::table('catalog_variants')->where('id', $variant->id)->update(['price_cents' => -1]),
            fn () => DB::table('catalog_variants')->where('id', $variant->id)->update(['is_reviewed' => false]),
            fn () => DB::table('catalog_options')->update(['price_cents' => null]),
        ];
        foreach ($attempts as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('Constraint deveria rejeitar alteração inválida.');
            } catch (QueryException $error) {
                $this->assertSame('23514', $error->errorInfo[0]);
            }
        }
        $this->assertGreaterThan(0, $variant->fresh()->price_cents);
    }
}

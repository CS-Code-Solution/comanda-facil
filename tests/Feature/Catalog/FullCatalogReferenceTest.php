<?php

namespace Tests\Feature\Catalog;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\BillingAccounts;
use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\ManageCatalog;
use App\Application\Catalog\QuoteCatalogItem;
use App\Application\Catalog\ReadCatalogReference;
use App\Application\Catalog\SyncCatalogReference;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use App\Enums\CatalogResource;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FullCatalogReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_does_not_reprice_a_confirmed_order_or_its_tickets(): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $variant = CatalogProduct::where('slug', 'pizzas-calabresa')->sole()->variants()->where('pizza_size', 'g')->sole();
        $variant->forceFill(['price_cents' => 7000])->save();
        $manager = User::factory()->create(['role' => 'gestor']);
        $context = PickupContext::staff($manager);
        $orders = app(PickupOrders::class);
        $orders->service(true, 'Abrir fixture de histórico', $context);
        $intent = $orders->preview(['phone' => '86999001122', 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]], $context);
        $order = $orders->confirm($intent->id, $context);
        $tickets = DB::table('print_jobs')->orderBy('id')->pluck('snapshot')->all();
        $history = DB::table('order_history')->orderBy('id')->pluck('after')->all();
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $this->assertSame(5300, $variant->fresh()->price_cents);
        $this->assertSame(7000, $order->fresh()->total_cents);
        $this->assertSame(7000, $order->items()->sole()->snapshot['total_cents']);
        $this->assertSame(7000, app(AccountBalance::class)->forAccount(BillingAccounts::forOrder($order))['total_cents']);
        $this->assertSame($tickets, DB::table('print_jobs')->orderBy('id')->pluck('snapshot')->all());
        $this->assertSame($history, DB::table('order_history')->orderBy('id')->pluck('after')->all());
    }

    public function test_duplicate_legacy_and_canonical_entries_require_reconciliation_without_partial_sync(): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $legacy = CatalogProduct::where('slug', 'pizzas-calabresa')->sole();
        app(ManageCatalog::class)->save(CatalogResource::Products, ['name' => 'Pizza calabresa', 'category_id' => $legacy->category_id,
            'type' => 'pizza', 'is_reviewed' => true], CatalogContext::console());
        $events = DB::table('catalog_events')->count();
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertFailed();
        $this->assertDatabaseCount('catalog_products', 9);
        $this->assertDatabaseCount('catalog_variants', 22);
        $this->assertDatabaseCount('catalog_events', $events);
    }

    private function reference(): array
    {
        return app(ReadCatalogReference::class)->read(File::get(base_path('docs/product/catalog-reference.md')));
    }

    public function test_document_has_one_product_per_row_and_prices_grouped_by_presentation(): void
    {
        $products = $this->reference();
        $this->assertCount(309, $products);
        $this->assertSame(479, array_sum(array_map(fn ($p) => count($p['variants']), $products)));
        $this->assertCount(309, array_unique(array_column($products, 'key')));
        $names = array_column($products, null, 'name');
        $this->assertSame(['p', 'g', 'f'], array_column($names['Pizza calabresa']['variants'], 'pizza_size'));
        $this->assertSame([250, 500, 750, 1000], array_column($names['Porção na brasa — picanha']['variants'], 'measure_value'));
        $this->assertSame([1, 2], array_column($names['Filé parisiense']['variants'], 'measure_value'));
        $this->assertSame(['inteira', 'meia'], array_column($names['Galinha caipira']['variants'], 'name'));
        $this->assertSame([null, null], array_column($names['Galinha caipira']['variants'], 'measure_value'));
        $this->assertSame([2000, 1500, null, null, 600], array_column($names['Coca-Cola']['variants'], 'measure_value'));
        $this->assertSame([269, 473], array_column($names['Monster']['variants'], 'measure_value'));
        $this->assertSame(2, $names['Harumaki de chocolate']['variants'][0]['measure_value']);
        $this->assertSame(50, array_sum(array_column($names['Combinado Família — 50 unidades']['composition'], 'quantity')));
    }

    public function test_full_publication_imports_all_rows_and_replay_has_no_writes_or_duplicates(): void
    {
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_products', 309);
        $this->assertDatabaseCount('catalog_variants', 479);
        $this->assertSame(309, CatalogProduct::where('is_active', true)->count());
        $this->assertSame(479, CatalogVariant::where('is_active', true)->count());
        $revision = DB::table('catalog_state')->value('revision');
        $events = DB::table('catalog_events')->count();
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_products', 309);
        $this->assertDatabaseCount('catalog_variants', 479);
        $this->assertSame($revision, DB::table('catalog_state')->value('revision'));
        $this->assertDatabaseCount('catalog_events', $events);
        $this->assertDatabaseCount('catalog_option_groups', 1);
        $this->assertDatabaseCount('catalog_options', 2);
        $this->artisan('catalog:import-reference', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_products', 309);
        $this->assertDatabaseCount('catalog_variants', 479);
        $this->assertSame($revision, DB::table('catalog_state')->value('revision'));
    }

    public function test_old_sample_ids_are_reused_even_after_operator_renames_products(): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $before = CatalogProduct::orderBy('id')->pluck('id', 'slug')->all();
        $variantIds = CatalogVariant::orderBy('id')->pluck('id')->all();
        $calabresa = CatalogProduct::where('slug', 'pizzas-calabresa')->sole();
        $calabresa->forceFill(['name' => 'Pizza de Calabresa', 'photo_path' => 'catalog/photos/existing.jpg', 'is_available' => false])->save();
        $coca = CatalogProduct::where('slug', 'bebidas-diversas-coca-cola')->sole();
        $coca->forceFill(['name' => 'Coca-Cola 1L'])->save();
        $variant = $coca->variants()->where('measure_value', 600)->sole();
        $variant->forceFill(['price_cents' => 1, 'is_available' => false])->save();
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_products', 309);
        $this->assertDatabaseCount('catalog_variants', 479);
        $this->assertSame($before, CatalogProduct::whereIn('id', array_values($before))->orderBy('id')->pluck('id', 'slug')->all());
        $this->assertSame($variantIds, CatalogVariant::whereIn('id', $variantIds)->orderBy('id')->pluck('id')->all());
        $this->assertSame('Pizza calabresa', $calabresa->fresh()->name);
        $this->assertSame('catalog/photos/existing.jpg', $calabresa->fresh()->photo_path);
        $this->assertFalse($calabresa->fresh()->is_available);
        $this->assertSame('Coca-Cola', $coca->fresh()->name);
        $this->assertSame(800, $variant->fresh()->price_cents);
        $this->assertFalse($variant->fresh()->is_available);
    }

    public function test_dry_run_does_not_change_database_or_audit(): void
    {
        Artisan::call('catalog:import-reference');
        $revision = DB::table('catalog_state')->value('revision');
        $this->artisan('catalog:sync-reference', ['--dry-run' => true, '--publish' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_products', 8);
        $this->assertDatabaseCount('catalog_variants', 22);
        $this->assertSame($revision, DB::table('catalog_state')->value('revision'));
        $this->assertSame(0, CatalogProduct::where('is_active', true)->count());
    }

    public function test_draft_mode_preserves_existing_publication_and_new_products_stay_draft(): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $this->artisan('catalog:sync-reference')->assertSuccessful();
        $this->assertSame(8, CatalogProduct::where('is_active', true)->count());
        $this->assertSame(301, CatalogProduct::where('is_active', false)->count());
    }

    public function test_absent_presentation_is_retired_without_deletion_and_custom_products_remain(): void
    {
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $coca = CatalogProduct::where('slug', 'bebidas-diversas-coca-cola')->sole();
        $catalog = app(ManageCatalog::class);
        $context = CatalogContext::console();
        $extra = $catalog->save(CatalogResource::Variants, ['product_id' => $coca->id, 'name' => '1 L', 'price' => '10,00',
            'unit' => 'ml', 'measure_value' => 1000, 'is_active' => true, 'is_available' => true, 'is_reviewed' => true], $context);
        $custom = $catalog->save(CatalogResource::Products, ['category_id' => $coca->category_id, 'name' => 'Cadastro manual adicional',
            'type' => 'simple', 'is_active' => true, 'is_available' => true, 'is_reviewed' => true], $context);
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $this->assertDatabaseHas('catalog_variants', ['id' => $extra->getKey(), 'is_active' => false]);
        $this->assertDatabaseHas('catalog_products', ['id' => $custom->getKey(), 'is_active' => true]);
        $this->assertSame(479, CatalogVariant::where('is_active', true)->count());
    }

    public function test_late_failure_rolls_back_all_catalog_writes(): void
    {
        $products = $this->reference();
        $products[array_key_last($products)]['variants'][0]['price'] = 'invalid';
        try {
            app(SyncCatalogReference::class)->execute($products, true);
            $this->fail('Invalid price must reject the import');
        } catch (ValidationException) {
            $this->assertDatabaseCount('catalog_products', 0);
            $this->assertDatabaseCount('catalog_variants', 0);
            $this->assertDatabaseCount('catalog_events', 0);
            $this->assertSame(0, DB::table('catalog_state')->value('revision'));
        }
    }

    public function test_malformed_table_price_cannot_be_silently_skipped(): void
    {
        $source = File::get(base_path('docs/product/catalog-reference.md'));
        $this->expectException(\RuntimeException::class);
        app(ReadCatalogReference::class)->read(str_replace('P R$ 32,00', 'P valor pendente', $source));
    }

    public function test_pizza_combo_and_churrasco_continue_using_backend_pricing(): void
    {
        $this->artisan('catalog:sync-reference', ['--publish' => true])->assertSuccessful();
        $first = CatalogProduct::where('name', 'Pizza portuguesa')->sole()->variants()->where('pizza_size', 'g')->sole();
        $second = CatalogProduct::where('name', 'Pizza chocolate com morango')->sole()->variants()->where('pizza_size', 'g')->sole();
        $quote = app(QuoteCatalogItem::class)->execute(['variant_id' => $first->id, 'second_variant_id' => $second->id, 'quantity' => 1], CatalogContext::console());
        $this->assertSame(5800, $quote['total_cents']);
        $combo = CatalogProduct::where('name', 'Combinado Família — 50 unidades')->sole();
        $this->assertSame(12500, $combo->variants()->sole()->price_cents);
        $this->assertSame(50, array_sum(array_column($combo->composition, 'quantity')));
        $churrascos = CatalogProduct::whereHas('category', fn ($q) => $q->where('name', 'Churrascos'))->get();
        $this->assertCount(13, $churrascos);
        foreach ($churrascos as $churrasco) {
            $group = $churrasco->optionGroups()->sole();
            $this->assertSame(1, $group->min_selections);
            $this->assertSame(['Batata', 'Macaxeira'], $group->options()->orderBy('id')->pluck('name')->all());
        }
    }
}

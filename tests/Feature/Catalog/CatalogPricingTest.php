<?php

namespace Tests\Feature\Catalog;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\ManageCatalog;
use App\Application\Catalog\QuoteCatalogItem;
use App\Enums\CatalogResource as Resource;
use App\Models\CatalogOption;
use App\Models\CatalogOptionGroup;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $this->manager = User::factory()->create(['role' => 'gestor']);
        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(15, 0));
    }

    private function variant(string $product, string $presentation): CatalogVariant
    {
        return CatalogVariant::query()->whereHas('product', fn ($query) => $query->where('name', $product))->where('name', $presentation)->firstOrFail();
    }

    private function quote(CatalogVariant $variant, array $extra = [], ?User $actor = null): array
    {
        return app(QuoteCatalogItem::class)->execute(['variant_id' => $variant->id, 'quantity' => 1, ...$extra], CatalogContext::web($actor ?? $this->manager));
    }

    private function promotion(array $extra = []): int
    {
        return app(ManageCatalog::class)->save(Resource::Promotions, [
            'name' => 'Promoção de teste', 'scope' => 'all', 'discount_type' => 'percentage', 'discount_value' => '10,00',
            'starts_at' => '2026-10-24T11:00', 'ends_at' => '2026-10-24T13:00', 'is_active' => true, ...$extra,
        ], CatalogContext::web($this->manager))->getKey();
    }

    public function test_two_flavor_pizza_uses_highest_full_price_in_either_order(): void
    {
        $calabresa = $this->variant('Calabresa', 'Grande');
        $shrimp = $this->variant('Camarão c/ catupiry', 'Grande');
        foreach ([[$calabresa, $shrimp], [$shrimp, $calabresa]] as [$first, $second]) {
            $quote = $this->quote($first, ['second_variant_id' => $second->id, 'quantity' => 2, 'price' => '0,01', 'total_cents' => 1]);
            $this->assertSame(5800, $quote['base_unit_cents']);
            $this->assertSame(11600, $quote['total_cents']);
            $this->assertCount(2, $quote['flavors']);
        }
        $this->assertSame(5300, $this->quote($calabresa)['total_cents']);
    }

    public function test_different_pizza_sizes_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Calabresa', 'Grande'), ['second_variant_id' => $this->variant('Frango catupiry', 'Pequena')->id]);
    }

    public function test_second_flavor_must_be_a_distinct_pizza_product(): void
    {
        $this->expectException(ValidationException::class);
        $pizza = $this->variant('Calabresa', 'Grande');
        $this->quote($pizza, ['second_variant_id' => $pizza->id]);
    }

    public function test_non_pizza_cannot_have_a_second_flavor(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Picanha', '500 g'), ['second_variant_id' => $this->variant('Calabresa', 'Grande')->id]);
    }

    public function test_weights_volumes_and_combo_are_fixed_price_presentations(): void
    {
        $this->assertSame(16000, $this->quote($this->variant('Picanha', '500 g'), ['quantity' => 2])['total_cents']);
        $this->assertSame(1300, $this->quote($this->variant('Coca-Cola', '1,5 L'))['total_cents']);
        $this->assertSame(800, $this->quote($this->variant('Laranja', 'Copo'))['total_cents']);
        $combo = CatalogProduct::query()->where('name', 'Simples')->firstOrFail()->variants()->firstOrFail();
        $quote = $this->quote($combo);
        $this->assertSame(2700, $quote['total_cents']);
        $this->assertSame(10, array_sum(array_column($quote['composition'], 'quantity')));
        $this->assertCount(3, $quote['composition']);
    }

    public function test_included_side_is_required_and_has_explicit_zero_price(): void
    {
        $side = CatalogOption::query()->where('name', 'Batata')->firstOrFail();
        $quote = $this->quote($this->variant('Picanha c/ queijo', '500 g'), ['options' => [$side->id => 1]]);
        $this->assertSame(12000, $quote['total_cents']);
        $this->assertSame(0, $quote['options_unit_cents']);
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Picanha c/ queijo', '500 g'));
    }

    public function test_option_group_maximum_is_enforced(): void
    {
        $this->expectException(ValidationException::class);
        $options = CatalogOption::query()->pluck('id')->mapWithKeys(fn ($id) => [$id => 1])->all();
        $this->quote($this->variant('Picanha c/ queijo', '500 g'), ['options' => $options]);
    }

    public function test_foreign_option_is_rejected_even_if_it_is_free(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Calabresa', 'Grande'), ['options' => [CatalogOption::query()->firstOrFail()->id => 1]]);
    }

    public function test_priced_addons_are_multiplied_then_discounted_without_float_math(): void
    {
        $catalog = app(ManageCatalog::class);
        $ctx = CatalogContext::web($this->manager);
        $group = $catalog->save(Resource::Groups, ['name' => 'Adicional de teste', 'min_selections' => 0, 'max_selections' => 3, 'is_active' => true], $ctx);
        $option = $catalog->save(Resource::Options, ['group_id' => $group->getKey(), 'name' => 'Extra sintético', 'price' => '2,55', 'max_quantity' => 3, 'is_reviewed' => true, 'is_available' => true], $ctx);
        $variant = $this->variant('Laranja', 'Copo');
        $variant->product->optionGroups()->attach($group);
        $quote = $this->quote($variant, ['quantity' => 3, 'options' => [$option->getKey() => 2], 'manual_discount' => ['type' => 'percentage', 'value' => '10,00']]);
        $this->assertSame(1310, $quote['gross_unit_cents']);
        $this->assertSame(393, $quote['discount_cents']);
        $this->assertSame(3537, $quote['total_cents']);
    }

    public function test_only_best_discount_applies_and_ties_keep_oldest_promotion(): void
    {
        $first = $this->promotion(['discount_type' => 'fixed', 'discount_value' => '8,00']);
        $this->promotion(['discount_type' => 'fixed', 'discount_value' => '8,00']);
        $this->promotion(['discount_value' => '10,00']);
        $variant = $this->variant('Calabresa', 'Grande');
        $quote = $this->quote($variant, ['manual_discount' => ['type' => 'fixed', 'value' => '8,00']]);
        $this->assertSame(4500, $quote['total_cents']);
        $this->assertSame($first, $quote['applied_discount']['id']);
        $quote = $this->quote($variant, ['manual_discount' => ['type' => 'fixed', 'value' => '10,00']]);
        $this->assertSame(4300, $quote['total_cents']);
        $this->assertSame('manual', $quote['applied_discount']['source']);
    }

    public function test_percentage_rounds_half_up_per_unit_before_quantity(): void
    {
        $variant = $this->variant('Laranja', 'Copo');
        $variant->forceFill(['price_cents' => 1005])->save();
        $this->promotion();
        $quote = $this->quote($variant, ['quantity' => 2]);
        $this->assertSame(101, $quote['discount_unit_cents']);
        $this->assertSame(1808, $quote['total_cents']);
    }

    public function test_fixed_discount_never_makes_total_negative(): void
    {
        $this->promotion(['discount_type' => 'fixed', 'discount_value' => '9999,00']);
        $quote = $this->quote($this->variant('Laranja', 'Copo'));
        $this->assertSame(800, $quote['discount_cents']);
        $this->assertSame(0, $quote['total_cents']);
    }

    public function test_promotion_uses_restaurant_timezone_and_half_open_interval(): void
    {
        $this->promotion(['starts_at' => '2026-10-24T12:00', 'ends_at' => '2026-10-24T12:01']);
        $variant = $this->variant('Laranja', 'Copo');
        $this->travelTo(now()->setTime(14, 59, 59));
        $this->assertSame(800, $this->quote($variant)['total_cents']);
        $this->travelTo(now()->setTime(15, 0, 0));
        $this->assertSame(720, $this->quote($variant)['total_cents']);
        $this->travelTo(now()->setTime(15, 1, 0));
        $this->assertSame(800, $this->quote($variant)['total_cents']);
    }

    public function test_product_promotion_cannot_discount_other_flavor(): void
    {
        $first = $this->variant('Calabresa', 'Grande');
        $this->promotion(['scope' => 'product', 'product_id' => $first->product_id, 'discount_value' => '50,00']);
        $this->assertSame(2650, $this->quote($first)['total_cents']);
        $this->assertSame(5800, $this->quote($first, ['second_variant_id' => $this->variant('Camarão c/ catupiry', 'Grande')->id])['total_cents']);
        $this->promotion(['scope' => 'category', 'category_id' => $first->product->category_id]);
        $this->assertSame(5220, $this->quote($first, ['second_variant_id' => $this->variant('Camarão c/ catupiry', 'Grande')->id])['total_cents']);
    }

    public function test_variant_promotion_is_limited_to_its_presentation(): void
    {
        $variant = $this->variant('Laranja', 'Copo');
        $this->promotion(['scope' => 'variant', 'variant_id' => $variant->id]);
        $this->assertSame(720, $this->quote($variant)['total_cents']);
        $this->assertSame(2300, $this->quote($this->variant('Laranja', 'Jarra'))['total_cents']);
    }

    public function test_unavailable_product_cannot_be_quoted_using_stale_form(): void
    {
        $variant = $this->variant('Calabresa', 'Grande');
        $variant->product->forceFill(['is_available' => false])->save();
        $this->expectException(ValidationException::class);
        $this->quote($variant);
    }

    public function test_unpublished_or_paused_variant_is_rejected(): void
    {
        $variant = $this->variant('Calabresa', 'Grande');
        $variant->forceFill(['is_active' => false])->save();
        $this->expectException(ValidationException::class);
        $this->quote($variant);
    }

    public function test_incompatible_pizza_option_groups_are_rejected(): void
    {
        $variant = $this->variant('Calabresa', 'Grande');
        $group = CatalogOptionGroup::query()->firstOrFail();
        $variant->product->optionGroups()->attach($group);
        $this->expectException(ValidationException::class);
        $this->quote($variant, ['second_variant_id' => $this->variant('Frango catupiry', 'Grande')->id]);
    }

    public function test_manual_percentage_above_one_hundred_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Laranja', 'Copo'), ['manual_discount' => ['type' => 'percentage', 'value' => '100,01']]);
    }

    public function test_inactive_promotion_does_not_discount(): void
    {
        $this->promotion(['is_active' => false]);
        $this->assertSame(800, $this->quote($this->variant('Laranja', 'Copo'))['total_cents']);
    }

    public function test_inactive_category_prevents_pricing(): void
    {
        $variant = $this->variant('Calabresa', 'Grande');
        $variant->product->category->forceFill(['is_active' => false])->save();
        $this->expectException(ValidationException::class);
        $this->quote($variant);
    }

    public function test_unavailable_required_options_prevent_pricing(): void
    {
        CatalogOption::query()->update(['is_available' => false]);
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Picanha c/ queijo', '500 g'));
    }

    public function test_waiter_cannot_apply_manual_discount_even_when_a_better_promotion_exists(): void
    {
        $this->promotion(['discount_value' => '50,00']);
        $this->expectException(AuthorizationException::class);
        $this->quote($this->variant('Calabresa', 'Grande'), ['manual_discount' => ['type' => 'fixed', 'value' => '1,00']], User::factory()->create());
    }

    public function test_context_reloads_actor_and_rejects_revoked_or_demoted_access(): void
    {
        $ctx = CatalogContext::web($this->manager);
        $this->manager->forceFill(['auth_version' => 2])->save();
        $this->expectException(AuthorizationException::class);
        app(QuoteCatalogItem::class)->execute(['variant_id' => $this->variant('Calabresa', 'Grande')->id, 'quantity' => 1], $ctx);
    }

    #[DataProvider('badQuantities')]
    public function test_invalid_quantities_are_rejected(mixed $quantity): void
    {
        $this->expectException(ValidationException::class);
        $this->quote($this->variant('Calabresa', 'Grande'), ['quantity' => $quantity]);
    }

    public static function badQuantities(): array
    {
        return [[0], [-1], [100], ['1.5']];
    }
}

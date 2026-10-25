<?php

namespace Tests\Feature\Catalog;

use App\Http\Presenters\DiningMenu;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DiningTestCase;

class PizzaFlavorChoicesTest extends DiningTestCase
{
    public function test_second_flavors_show_only_names_and_match_each_first_size(): void
    {
        $menu = collect(DiningMenu::products())->firstWhere('id', $this->variant->product_id);
        foreach ($menu['variants'] as $first) {
            $this->assertCount(2, $first['secondFlavors']);
            $this->assertEqualsCanonicalizing(['Camarão c/ catupiry', 'Frango catupiry'], array_column($first['secondFlavors'], 'name'));
            $size = CatalogVariant::findOrFail($first['id'])->pizza_size;
            foreach ($first['secondFlavors'] as $choice) {
                $second = CatalogVariant::findOrFail($choice['id']);
                $this->assertSame($size, $second->pizza_size);
                $this->assertSame($second->product_id, $choice['productId']);
                $this->assertNotSame($this->variant->product_id, $choice['productId']);
            }
        }
    }

    public function test_unavailable_size_does_not_hide_the_flavor_in_other_sizes(): void
    {
        $shrimp = CatalogProduct::where('name', 'Camarão c/ catupiry')->sole();
        $shrimp->variants()->where('pizza_size', 'g')->update(['is_available' => false]);
        $menu = collect(DiningMenu::products())->firstWhere('id', $this->variant->product_id);
        foreach ($menu['variants'] as $first) {
            $this->assertCount($first['id'] === $this->variant->id ? 1 : 2, $first['secondFlavors']);
            if ($first['id'] === $this->variant->id) {
                $this->assertSame('Frango catupiry', $first['secondFlavors'][0]['name']);
            }
        }
    }

    public function test_catalog_detail_and_order_menu_offer_identical_second_flavors(): void
    {
        $menu = collect(DiningMenu::products())->firstWhere('id', $this->variant->product_id);
        $this->signIn($this->manager)->get(route('catalog.show', $this->variant->product_id))
            ->assertInertia(fn (Assert $page) => $page->component('catalog/show')
                ->where('variants', fn ($actual): bool => collect($actual)->keyBy('id')->all() == collect($menu['variants'])->keyBy('id')->all())
                ->missing('secondVariants'));
    }
}

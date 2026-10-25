<?php

namespace Tests\Support;

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\GuestGrant;
use App\Application\Dining\ManageDining;
use App\Models\CatalogVariant;
use App\Models\DiningOrderIntent;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class DiningTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $waiter;

    protected DiningTable $table;

    protected CatalogVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $this->manager = User::factory()->create(['role' => 'gestor']);
        $this->waiter = User::factory()->create(['role' => 'garcom']);
        $manage = app(ManageDining::class);
        $this->table = $manage->createTable(['number' => 7], DiningContext::staff($this->manager));
        $manage->service(true, 'Abrir teste', DiningContext::staff($this->manager));
        $this->variant = CatalogVariant::query()->whereHas('product', fn ($query) => $query->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
    }

    protected function grant(?DiningTable $table = null): GuestGrant
    {
        $table ??= $this->table->fresh();

        return new GuestGrant($table->id, $table->qr_version, $table->cycle, (string) Str::uuid(), now()->addHours(2)->timestamp);
    }

    protected function preview(?DiningContext $context = null, array $extra = []): DiningOrderIntent
    {
        return app(DiningOrders::class)->preview($this->table->id, ['cycle' => $this->table->fresh()->cycle,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'note' => 'Sem observação adicional']], ...$extra], $context ?? DiningContext::staff($this->waiter));
    }
}

<?php

namespace App\Console\Commands;

use App\Application\Cash\CashShifts;
use App\Application\Delivery\DeliveryCoverage;
use App\Application\Dining\DiningContext;
use App\Application\Dining\ManageDining;
use App\Application\Operations\PrivateFiles;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class PreparePilotFixture extends Command
{
    protected $signature = 'pilot:fixture';

    protected $description = 'Preparar APENAS banco vazio testing com prefixo comanda_facil_pilot_ para testes integrados';

    public function handle(): int
    {
        $database = config('database.connections.pgsql.database');
        if (! app()->environment('testing') || ! is_string($database) || ! preg_match('/^comanda_facil_pilot_[a-z0-9_]+$/', $database)
            || ! empty(config('database.connections.pgsql.url')) || config('database.default') !== 'pgsql') {
            $this->error('Fixture exige APP_ENV=testing, DB_URL vazio e banco exclusivo com prefixo comanda_facil_pilot_.');

            return self::FAILURE;
        }
        foreach (['users', 'dining_tables', 'dining_orders', 'catalog_products', 'cash_payments'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('O banco de fixture precisa estar vazio. Nenhum dado existente será apagado.');
            }
        }
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('catalog:sync-reference', ['--publish' => true]);
        $users = [];
        foreach (['gestor', 'caixa', 'garcom', 'cozinha', 'entregador'] as $role) {
            $users[$role] = User::factory()->create(['name' => 'Piloto QA '.$role, 'username' => 'pilot.'.$role, 'role' => $role, 'pin_hash' => '048269']);
        }
        $context = DiningContext::staff($users['gestor']);
        $dining = app(ManageDining::class);
        $table = $dining->createTable(['number' => 7, 'label' => 'Fixture de teste integrado'], $context)->fresh();
        $dining->service(true, 'Fixture sintética de teste integrado', $context);
        app(PickupOrders::class)->service(true, 'Fixture sintética de teste integrado', PickupContext::staff($users['gestor']));
        app(DeliveryCoverage::class)->save(null, ['name' => 'Área QA', 'city' => 'Cidade QA', 'state' => 'PI', 'neighborhood' => 'Centro',
            'fee' => '7,00', 'minimum' => '40,00', 'is_active' => true, 'reason' => 'Fixture sintética de teste integrado'], $context);
        app(DeliveryCoverage::class)->service(true, 'Fixture sintética de teste integrado', $context);
        $shift = app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100,00'], $context);
        $directory = storage_path('framework/testing');
        PrivateFiles::directory($directory);
        file_put_contents($directory.'/pilot-fixture.json', json_encode(['table' => $table->id, 'shift' => $shift->id,
            'qr' => '/m/'.$table->qr_token, 'courier' => $users['entregador']->id], JSON_THROW_ON_ERROR));
        PrivateFiles::file($directory.'/pilot-fixture.json');
        $this->info('Fixture sintética preparada no banco isolado; metadados no diretório de testes ignorado.');

        return self::SUCCESS;
    }
}

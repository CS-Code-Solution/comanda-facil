<?php

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\CatalogVariant;
use App\Models\DiningOrder;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.pgsql.database');
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage04_')) {
    throw new RuntimeException('Use APP_ENV=testing e banco exclusivo novo com prefixo comanda_facil_stage04_.');
}
$barrier = 2026102406;
if (($argv[1] ?? '') === 'worker') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage04-worker-'.$argv[2]]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    try {
        $ctx = DiningContext::staff(User::query()->findOrFail((int) $argv[3]));
        $service = app(OrderFulfillment::class);
        if ($argv[4] === 'edit') {
            $service->confirm((int) $argv[5], $argv[6], $ctx);
        } else {
            $service->transition((int) $argv[5], ['operation_id' => (string) Str::uuid(), 'version' => (int) $argv[6],
                'status' => $argv[4], 'reason' => 'Concorrência controlada'], $ctx);
        }
        echo 'committed';
    } catch (ValidationException) {
        echo 'stale';
    } finally {
        DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);
    }
    exit(0);
}
if (User::query()->exists() || DB::table('dining_tables')->exists()) {
    throw new RuntimeException('Banco precisa estar vazio, apenas migrado.');
}
Artisan::call('catalog:import-reference', ['--publish' => true]);
$manager = app(ManageStaffAccount::class)->create('QA Concorrência', 'qa.concorrencia', StaffRole::Manager, '048269', '048269', StaffAccessContext::console());
$context = DiningContext::staff($manager);
$manage = app(ManageDining::class);
$manage->service(true, 'Abrir teste de preparo', $context);
$variant = CatalogVariant::query()->whereHas('product', fn ($q) => $q->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database,
    'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? ''];
foreach (['edit-versus-prepare', 'cancel-versus-ready'] as $i => $scenario) {
    $table = $manage->createTable(['number' => 70 + $i], $context);
    $input = ['cycle' => 0, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]];
    $intent = app(DiningOrders::class)->preview($table->id, $input, $context);
    $order = app(DiningOrders::class)->confirm($intent->id, $table->id, $context);
    if ($i === 0) {
        $change = app(OrderFulfillment::class)->preview($order->id, ['version' => 1, 'reason' => 'Aumentar quantidade', 'items' => [['variant_id' => $variant->id, 'quantity' => 2]]], $context);
        $commands = [['edit', $change], ['preparing', '1']];
    } else {
        app(OrderFulfillment::class)->transition($order->id, ['version' => 1, 'operation_id' => (string) Str::uuid(), 'status' => 'preparing'], $context);
        $commands = [['cancelled', '2'], ['ready', '2']];
    }
    DB::select('select pg_advisory_lock(?)', [$barrier]);
    $workers = [];
    try {
        foreach ($commands as $index => [$command, $argument]) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $index, (string) $manager->id, $command, (string) $order->id, $argument], base_path(), $env, timeout: 30);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 15;
        do {
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage04-worker-%' and wait_event_type='Lock'");
            if ((int) $waiting->total === 2) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        if ((int) $waiting->total !== 2) {
            throw new RuntimeException('Workers não alcançaram a barreira.');
        }
    } finally {
        DB::select('select pg_advisory_unlock(?)', [$barrier]);
    }
    $results = [];
    foreach ($workers as $worker) {
        if ($worker->wait() !== 0) {
            throw new RuntimeException($worker->getErrorOutput().$worker->getOutput());
        }
        $results[] = $worker->getOutput();
    }
    sort($results);
    $current = DiningOrder::query()->findOrFail($order->id);
    if ($results !== ['committed', 'stale'] || $current->version !== $i + 2 || DB::table('order_history')->where('order_id', $order->id)->count() !== $i + 2) {
        throw new RuntimeException('Comandos concorrentes sobrescreveram estado/histórico.');
    }
    if ($i === 0 && (($current->status === 'preparing' && $current->total_cents !== 5300) || ($current->status === 'confirmed' && $current->total_cents !== 10600))) {
        throw new RuntimeException('Correção atravessou início de preparo.');
    }
    echo "PASS: {$scenario}, um comando confirmado e outro recusado; histórico consistente.".PHP_EOL;
}

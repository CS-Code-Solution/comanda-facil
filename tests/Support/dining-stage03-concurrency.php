<?php

use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\GuestGrant;
use App\Application\Dining\ManageDining;
use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\CatalogVariant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.pgsql.database');
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage03_')) {
    fwrite(STDERR, 'Use APP_ENV=testing e banco novo exclusivo com prefixo comanda_facil_stage03_.'.PHP_EOL);
    exit(2);
}
$barrier = 2026102405;
if (($argv[1] ?? '') === 'worker') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage03-worker-'.$argv[2]]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    try {
        $grant = new GuestGrant((int) $argv[3], 1, 0, $argv[4], now()->addHours(2)->timestamp);
        $order = app(DiningOrders::class)->confirm($argv[5], (int) $argv[3], DiningContext::guest($grant));
        echo json_encode(['order' => $order->id, 'session' => $order->session_id], JSON_THROW_ON_ERROR);
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
$ctx = DiningContext::staff($manager);
$manage = app(ManageDining::class);
$manage->service(true, 'Abrir teste concorrente', $ctx);
$variant = CatalogVariant::query()->whereHas('product', fn ($query) => $query->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database,
    'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? ''];
foreach ([true, false] as $sameIntent) {
    $table = $manage->createTable(['number' => $sameIntent ? 7 : 8], $ctx);
    $grant = new GuestGrant($table->id, 1, 0, (string) Str::uuid(), now()->addHours(2)->timestamp);
    $guest = DiningContext::guest($grant);
    $data = ['cycle' => 0, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]];
    $first = app(DiningOrders::class)->preview($table->id, $data, $guest);
    $second = $sameIntent ? $first : app(DiningOrders::class)->preview($table->id, $data, $guest);
    $workers = [];
    DB::select('select pg_advisory_lock(?)', [$barrier]);
    try {
        foreach ([$first, $second] as $i => $intent) {
            $worker = new Process([PHP_BINARY, __FILE__, 'worker', (string) $i, (string) $table->id, $grant->visitor, $intent->id], base_path(), $env, timeout: 30);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        do {
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage03-worker-%' and wait_event_type='Lock'");
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
        $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
    $count = DB::table('dining_orders')->where('table_id', $table->id)->count();
    if ($count !== ($sameIntent ? 1 : 2) || $results[0]['session'] !== $results[1]['session'] || DB::table('dining_sessions')->where('table_id', $table->id)->whereNull('closed_at')->count() !== 1) {
        throw new RuntimeException('Pedido duplicado ou abertura concorrente de sessões inválida.');
    }
    echo $sameIntent ? 'PASS: dois reenvios simultâneos geraram um pedido e uma sessão.'.PHP_EOL : 'PASS: duas intenções simultâneas geraram dois pedidos na mesma sessão.'.PHP_EOL;
}

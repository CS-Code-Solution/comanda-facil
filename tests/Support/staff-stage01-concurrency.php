<?php

// Verificação manual: somente em banco descartável com o prefixo exigido abaixo.
use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.pgsql.database');

if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage01_')) {
    fwrite(STDERR, 'Use APP_ENV=testing e um banco exclusivo com prefixo comanda_facil_stage01_.'.PHP_EOL);
    exit(2);
}

$barrier = 2026102402;

if (($argv[1] ?? '') === 'worker') {
    $username = $argv[2] ?? '';
    DB::select("select set_config('application_name', ?, false)", ['cf-stage01-worker-'.$username]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);

    $code = 0;
    try {
        $user = User::query()->where('username', $username)->sole();
        app(ManageStaffAccount::class)->disable($user, StaffAccessContext::console());
    } catch (ValidationException) {
        $code = 3;
    } finally {
        DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);
    }
    exit($code);
}

if (User::query()->exists()) {
    throw new RuntimeException('O banco de verificação precisa estar vazio.');
}

$accounts = app(ManageStaffAccount::class);
foreach (['qa.gestor1', 'qa.gestor2'] as $username) {
    $accounts->create('QA '.$username, $username, StaffRole::Manager, '048269', '048269', StaffAccessContext::console());
}

$env = [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
    'DB_DATABASE' => $database, 'DB_HOST' => config('database.connections.pgsql.host'),
    'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'),
    'DB_PASSWORD' => config('database.connections.pgsql.password') ?? '',
];
$workers = [];
DB::select('select pg_advisory_lock(?)', [$barrier]);

try {
    foreach (['qa.gestor1', 'qa.gestor2'] as $username) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', $username], base_path(), $env, timeout: 30);
        $process->start();
        $workers[] = $process;
    }

    $deadline = microtime(true) + 20;
    do {
        $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage01-worker-%' and wait_event_type = 'Lock'");
        if ((int) $waiting->total === 2) {
            break;
        }
        usleep(50000);
    } while (microtime(true) < $deadline);

    if ((int) $waiting->total !== 2) {
        throw new RuntimeException('Os dois workers não alcançaram a barreira de concorrência.');
    }
} finally {
    DB::select('select pg_advisory_unlock(?)', [$barrier]);
}

$results = array_map(fn (Process $worker): int => $worker->wait(), $workers);
sort($results);
$active = User::query()->where('role', 'gestor')->where('is_active', true)->get();

if ($results !== [0, 3] || $active->count() !== 1) {
    throw new RuntimeException('Falhou a proteção concorrente do último gestor: '.json_encode($results));
}

echo json_encode(['result' => 'passed', 'worker_exits' => $results, 'active_managers' => 1, 'active_username' => $active->sole()->username], JSON_THROW_ON_ERROR).PHP_EOL;

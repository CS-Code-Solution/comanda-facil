<?php

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashReconciliation;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Application\Orders\OrderFulfillment;
use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Domain\Catalog\Money;
use App\Enums\StaffRole;
use App\Models\CashPayment;
use App\Models\CashShift;
use App\Models\CatalogVariant;
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
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage05_')) {
    throw new RuntimeException('Use APP_ENV=testing e banco novo exclusivo com prefixo comanda_facil_stage05_.');
}
$barrier = 2026102408;
if (($argv[1] ?? '') === 'worker') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage05-worker-'.$argv[2]]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    try {
        $context = DiningContext::staff(User::query()->findOrFail((int) $argv[3]));
        $input = json_decode($argv[6], true, flags: JSON_THROW_ON_ERROR);
        match ($argv[4]) {
            'open' => app(CashShifts::class)->open($input, $context),
            'close' => app(CashShifts::class)->close((int) $argv[5], $input, $context),
            'pay' => app(CashPayments::class)->confirm((int) $argv[5], $input['intent'], ['method' => 'pix'], $context),
            'refund' => app(CashPayments::class)->refund((int) $argv[5], $input, $context),
            'cancel' => app(OrderFulfillment::class)->transition((int) $argv[5], $input, $context),
        };
        echo 'committed';
    } catch (ValidationException) {
        echo 'rejected';
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
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database,
    'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? ''];
$race = function (array $commands, string $label, bool $oneWinner = true) use ($env, $manager, $barrier): array {
    $workers = [];
    DB::select('select pg_advisory_lock(?)', [$barrier]);
    try {
        foreach ($commands as $i => [$kind, $resource, $input]) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $i, (string) $manager->id, $kind,
                (string) $resource, json_encode($input, JSON_THROW_ON_ERROR)], base_path(), $env, timeout: 30);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 15;
        do {
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage05-worker-%' and wait_event_type='Lock'");
            if ((int) $waiting->total === 2) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        if ((int) $waiting->total !== 2) {
            throw new RuntimeException('Workers não chegaram à barreira.');
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
    if ($oneWinner && $results !== ['committed', 'rejected']) {
        throw new RuntimeException($label.': esperava um comando confirmado e outro rejeitado.');
    }
    echo 'PASS: '.$label.' => '.implode(', ', $results).PHP_EOL;

    return $results;
};
$race([['open', 0, ['operation_id' => (string) Str::uuid(), 'opening' => '100']],
    ['open', 0, ['operation_id' => (string) Str::uuid(), 'opening' => '100']]], 'abertura simultânea');
if (CashShift::query()->whereNull('closed_at')->count() !== 1) {
    throw new RuntimeException('Mais de um turno aberto.');
}
$manage = app(ManageDining::class);
$manage->service(true, 'Abrir fixture de caixa', $context);
$variant = CatalogVariant::query()->whereHas('product', fn ($q) => $q->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$makeOrder = function (int $number) use ($manage, $context, $variant) {
    $table = $manage->createTable(['number' => $number], $context);
    $intent = app(DiningOrders::class)->preview($table->id, ['cycle' => 0, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]], $context);

    return app(DiningOrders::class)->confirm($intent->id, $table->id, $context);
};
$order = $makeOrder(81);
$first = app(CashPayments::class)->preview($order->session_id, [], $context);
$second = app(CashPayments::class)->preview($order->session_id, [], $context);
$race([['pay', $order->session_id, ['intent' => $first]], ['pay', $order->session_id, ['intent' => $second]]], 'duas cobranças da mesma conta');
if (CashPayment::query()->where('session_id', $order->session_id)->count() !== 1) {
    throw new RuntimeException('Cobrança duplicada.');
}
$payment = CashPayment::query()->where('session_id', $order->session_id)->sole();
app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => 'Cancelamento da fixture'], $context);
$refund = fn () => ['operation_id' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '40', 'reason' => 'Devolução simulada da fixture', 'performed' => true];
$race([['refund', $payment->id, $refund()], ['refund', $payment->id, $refund()]], 'duas devoluções acima do saldo');
if ((int) DB::table('cash_refunds')->where('payment_id', $payment->id)->sum('amount_cents') !== 4000) {
    throw new RuntimeException('Saída de caixa excedeu saldo autorizado.');
}
$nextOrder = $makeOrder(82);
$intent = app(CashPayments::class)->preview($nextOrder->session_id, [], $context);
$shift = CashShift::query()->whereNull('closed_at')->sole();
$counted = [];
foreach (app(CashReconciliation::class)->totals($shift) as $method => $row) {
    $amount = $row['expected'];
    $counted[$method] = ($amount < 0 ? '-' : '').Money::decimal(abs($amount));
}
$race([['close', $shift->id, ['operation_id' => (string) Str::uuid(), 'version' => $shift->version, 'counted' => $counted]],
    ['pay', $nextOrder->session_id, ['intent' => $intent]]], 'fechamento contra recebimento');
if (! CashShift::query()->whereNull('closed_at')->exists()) {
    app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '100'], $context);
}
$lastOrder = $makeOrder(83);
$intent = app(CashPayments::class)->preview($lastOrder->session_id, [], $context);
$race([['pay', $lastOrder->session_id, ['intent' => $intent]], ['cancel', $lastOrder->id,
    ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => 'Cancelamento simultâneo']]], 'pagamento contra cancelamento', false);
$lastOrder->refresh();
$lastPayment = CashPayment::query()->where('session_id', $lastOrder->session_id)->first();
if ($lastOrder->status !== 'cancelled' || ($lastPayment && ! $lastPayment->has_pending_refund) || CashPayment::query()->where('session_id', $lastOrder->session_id)->count() > 1) {
    throw new RuntimeException('Cancelamento/pagamento perdeu estado financeiro.');
}
echo 'PASS: cancelamento preservado; pagamento ausente ou único com devolução pendente, sem saída fictícia.'.PHP_EOL;

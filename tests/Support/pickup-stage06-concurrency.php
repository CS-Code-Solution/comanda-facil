<?php

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderFulfillment;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\CashPayment;
use App\Models\CatalogVariant;
use App\Models\DiningOrder;
use App\Models\PickupAccount;
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
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage06_')) {
    throw new RuntimeException('Use APP_ENV=testing e banco novo exclusivo com prefixo comanda_facil_stage06_.');
}
$barrier = 2026102409;
if (($argv[1] ?? '') === 'worker') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage06-worker-'.$argv[2]]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    try {
        $context = DiningContext::staff(User::query()->findOrFail((int) $argv[3]));
        $input = json_decode($argv[6], true, flags: JSON_THROW_ON_ERROR);
        match ($argv[4]) {
            'pay' => app(CashPayments::class)->confirm((int) $argv[5], $input['intent'], ['method' => 'pix'], $context, 'pickup'),
            'pickup' => app(PickupOrders::class)->confirm($input['intent'], PickupContext::staff(User::findOrFail((int) $argv[3]))),
            'transition' => app(OrderFulfillment::class)->transition((int) $argv[5], $input, $context),
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
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage06-worker-%' and wait_event_type='Lock'");
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

app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '0'], $context);
app(PickupOrders::class)->service(true, 'Abrir concorrência', PickupContext::staff($manager));
$variant = CatalogVariant::whereHas('product', fn ($q) => $q->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$pickupContext = PickupContext::staff($manager);
$preview = fn () => app(PickupOrders::class)->preview(['phone' => '86999001122', 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]], $pickupContext);
$intent = $preview();
$results = $race([['pickup', 0, ['intent' => $intent->id]], ['pickup', 0, ['intent' => $intent->id]]], 'confirmação simultânea da mesma intenção', false);
if ($results !== ['committed', 'committed'] || PickupAccount::count() !== 1 || DB::table('dining_orders')->count() !== 1 || DB::table('print_jobs')->count() !== 2) {
    throw new RuntimeException('Confirmação duplicou pedido/conta/comprovantes.');
}
$order = DiningOrder::sole();
$first = app(CashPayments::class)->preview($order->pickup_account_id, [], $context, 'pickup');
$second = app(CashPayments::class)->preview($order->pickup_account_id, [], $context, 'pickup');
$race([['pay', $order->pickup_account_id, ['intent' => $first]], ['pay', $order->pickup_account_id, ['intent' => $second]]], 'dois pagamentos de retirada');
if (CashPayment::count() !== 1) {
    throw new RuntimeException('Duplicou pagamento.');
}
app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'preparing'], $context);
app(OrderFulfillment::class)->transition($order->id, ['operation_id' => (string) Str::uuid(), 'version' => 2, 'status' => 'ready'], $context);
$collect = fn () => ['operation_id' => (string) Str::uuid(), 'version' => 3, 'status' => 'collected'];
$race([['transition', $order->id, $collect()], ['transition', $order->id, $collect()]], 'duas liberações da mesma versão');
if ($order->fresh()->status !== 'collected' || DB::table('order_history')->where('order_id', $order->id)->count() !== 4 || ! PickupAccount::findOrFail($order->pickup_account_id)->closed_at) {
    throw new RuntimeException('Liberação inconsistente.');
}
$intent = $preview();
$other = app(PickupOrders::class)->confirm($intent->id, $pickupContext);
app(OrderFulfillment::class)->transition($other->id, ['operation_id' => (string) Str::uuid(), 'version' => 1, 'status' => 'preparing'], $context);
app(OrderFulfillment::class)->transition($other->id, ['operation_id' => (string) Str::uuid(), 'version' => 2, 'status' => 'ready'], $context);
$paymentIntent = app(CashPayments::class)->preview($other->pickup_account_id, [], $context, 'pickup');
$results = $race([['pay', $other->pickup_account_id, ['intent' => $paymentIntent]], ['transition', $other->id, $collect()]], 'pagamento contra liberação', false);
if (! in_array('committed', $results, true)) {
    throw new RuntimeException('Pagamento não confirmou.');
}
$other->refresh();
if ($other->status === 'collected' && ! CashPayment::where('pickup_account_id', $other->pickup_account_id)->exists()) {
    throw new RuntimeException('Liberou sem pagamento.');
}
if ($other->status === 'ready') {
    app(OrderFulfillment::class)->transition($other->id, $collect(), $context);
}
echo 'PASS: pagamento integral precede retirada; reenvio seguro e uma conta por pedido.'.PHP_EOL;

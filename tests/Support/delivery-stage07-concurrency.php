<?php

use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryCoverage;
use App\Application\Delivery\DeliveryDispatch;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderFulfillment;
use App\Models\CashPayment;
use App\Models\CatalogVariant;
use App\Models\DeliveryAccount;
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
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage07_concurrency_')) {
    throw new RuntimeException('Use APP_ENV=testing e banco novo exclusivo com prefixo comanda_facil_stage07_concurrency_.');
}
$barrier = 2026102507;
if (($argv[1] ?? '') === 'worker') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage07-worker-'.$argv[2]]);
    DB::select('select pg_advisory_lock_shared(?)', [$barrier]);
    try {
        $user = User::findOrFail((int) $argv[3]);
        $context = DiningContext::staff($user);
        $input = json_decode($argv[6], true, flags: JSON_THROW_ON_ERROR);
        match ($argv[4]) {
            'confirm' => app(DeliveryOrders::class)->confirm($input['intent'], DeliveryContext::staff($user)),
            'pay' => app(CashPayments::class)->confirm((int) $argv[5], $input['intent'], ['method' => 'pix'], $context, 'delivery'),
            'transition' => app(OrderFulfillment::class)->transition((int) $argv[5], $input, $context),
            'area' => app(DeliveryCoverage::class)->save(null, $input, $context),
        };
        echo 'committed';
    } catch (ValidationException) {
        echo 'rejected';
    } finally {
        DB::select('select pg_advisory_unlock_shared(?)', [$barrier]);
    }
    exit(0);
}
if (User::query()->exists() || DB::table('delivery_areas')->exists()) {
    throw new RuntimeException('Banco precisa estar vazio, apenas migrado.');
}
Artisan::call('catalog:import-reference', ['--publish' => true]);
$manager = User::factory()->create(['role' => 'gestor']);
$courier = User::factory()->create(['role' => 'entregador']);
$context = DiningContext::staff($manager);
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database,
    'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? ''];
$race = function (array $commands, string $label, array $expected) use ($env, $barrier): void {
    $workers = [];
    DB::select('select pg_advisory_lock(?)', [$barrier]);
    try {
        foreach ($commands as $i => [$actor, $kind, $resource, $input]) {
            $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $i, (string) $actor, $kind,
                (string) $resource, json_encode($input, JSON_THROW_ON_ERROR)], base_path(), $env, timeout: 30);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 15;
        do {
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name like 'cf-stage07-worker-%' and wait_event_type='Lock'");
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
    if ($results !== $expected) {
        throw new RuntimeException($label.': resultado inesperado '.json_encode($results));
    }
    echo 'PASS: '.$label.' => '.implode(', ', $results).PHP_EOL;
};
$area = ['name' => 'Área QA', 'city' => 'Cidade QA', 'state' => 'PI', 'neighborhood' => 'Centro',
    'fee' => '7,00', 'minimum' => '40,00', 'is_active' => true, 'reason' => 'Fixture exclusiva'];
$race([[$manager->id, 'area', 0, $area], [$manager->id, 'area', 0, $area]], 'regras sobrepostas simultâneas', ['committed', 'rejected']);
app(DeliveryCoverage::class)->service(true, 'Abrir fixture', $context);
app(CashShifts::class)->open(['operation_id' => (string) Str::uuid(), 'opening' => '0'], $context);
$variant = CatalogVariant::whereHas('product', fn ($q) => $q->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$input = ['phone' => '86999001122', 'address' => ['recipient' => 'Pessoa QA', 'street' => 'Rua QA', 'number' => '10',
    'neighborhood' => 'Centro', 'city' => 'Cidade QA', 'state' => 'PI', 'cep' => '64000000'], 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]];
$intent = app(DeliveryOrders::class)->preview($input, DeliveryContext::staff($manager));
$race([[$manager->id, 'confirm', 0, ['intent' => $intent->id]], [$manager->id, 'confirm', 0, ['intent' => $intent->id]]], 'replay de confirmação simultâneo', ['committed', 'committed']);
if (DeliveryAccount::count() !== 1 || DiningOrder::count() !== 1 || DB::table('print_jobs')->count() !== 2) {
    throw new RuntimeException('Duplicou pedido, conta ou comprovantes.');
}
$order = DiningOrder::sole();
$move = fn (string $status, User $actor) => app(OrderFulfillment::class)->transition($order->id,
    ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(), 'status' => $status], DiningContext::staff($actor));
$move('preparing', $manager);
$move('ready', $manager);
app(DeliveryDispatch::class)->assign($order->id, ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(),
    'courier_id' => $courier->id, 'reason' => 'Atribuição QA'], $context);
$move('waiting_courier', $manager);
$move('dispatched', $courier);
$pay = app(CashPayments::class);
$courierContext = DiningContext::staff($courier);
$first = $pay->preview($order->delivery_account_id, [], $courierContext, 'delivery');
$second = $pay->preview($order->delivery_account_id, [], $courierContext, 'delivery');
$race([[$courier->id, 'pay', $order->delivery_account_id, ['intent' => $first]],
    [$courier->id, 'pay', $order->delivery_account_id, ['intent' => $second]]], 'recebimentos simultâneos', ['committed', 'rejected']);
$complete = fn () => ['version' => $order->fresh()->version, 'operation_id' => (string) Str::uuid(), 'status' => 'delivered'];
$race([[$courier->id, 'transition', $order->id, $complete()], [$courier->id, 'transition', $order->id, $complete()]],
    'conclusões da mesma versão', ['committed', 'rejected']);
if (CashPayment::count() !== 1 || $order->fresh()->status !== 'delivered' || ! DeliveryAccount::sole()->closed_at) {
    throw new RuntimeException('Entrega/recebimento inconsistentes.');
}
echo 'PASS: uma conta, um recebimento e conclusão após quitação.'.PHP_EOL;

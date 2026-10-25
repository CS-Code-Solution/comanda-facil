<?php

// Executar somente com APP_ENV=testing em banco novo e exclusivo da etapa 02.
use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\CatalogLock;
use App\Application\Catalog\ManageCatalog;
use App\Application\Catalog\QuoteCatalogItem;
use App\Domain\Catalog\Money;
use App\Enums\CatalogResource;
use App\Models\CatalogVariant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.pgsql.database');
if (! app()->environment('testing') || ! is_string($database) || ! str_starts_with($database, 'comanda_facil_stage02_')) {
    fwrite(STDERR, 'Use APP_ENV=testing e banco descartável com prefixo comanda_facil_stage02_.'.PHP_EOL);
    exit(2);
}

if (($argv[1] ?? '') === 'reader') {
    DB::select("select set_config('application_name', ?, false)", ['cf-stage02-quote-reader']);
    $quote = app(QuoteCatalogItem::class)->execute(['variant_id' => (int) $argv[2], 'quantity' => 1], CatalogContext::console());
    echo json_encode(['total' => $quote['total_cents'], 'revision' => $quote['revision']], JSON_THROW_ON_ERROR);
    exit(0);
}

$variant = CatalogVariant::query()->whereHas('product', fn ($query) => $query->where('name', 'Calabresa'))->where('pizza_size', 'g')->sole();
$oldPrice = $variant->price_cents;
$input = ['product_id' => $variant->product_id, 'name' => $variant->name, 'unit' => $variant->unit, 'pizza_size' => $variant->pizza_size,
    'is_reviewed' => $variant->is_reviewed, 'is_active' => $variant->is_active, 'is_available' => $variant->is_available];
$env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_DATABASE' => $database,
    'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
    'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password') ?? ''];
$reader = new Process([PHP_BINARY, __FILE__, 'reader', (string) $variant->id], base_path(), $env, timeout: 20);

try {
    DB::transaction(function () use ($variant, $input, $oldPrice, $reader) {
        CatalogLock::exclusive();
        app(ManageCatalog::class)->save(CatalogResource::Variants, [...$input, 'price' => Money::decimal($oldPrice + 100)], CatalogContext::console(), $variant->id);
        $reader->start();
        $deadline = microtime(true) + 10;
        do {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("select count(*) as total from pg_stat_activity where application_name='cf-stage02-quote-reader' and wait_event_type='Lock'");
            if ((int) $waiting->total === 1) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        if ((int) $waiting->total !== 1) {
            throw new RuntimeException('Leitor não aguardou o bloqueio transacional do catálogo. '.$reader->getErrorOutput().$reader->getOutput());
        }
    });
    if ($reader->wait() !== 0) {
        throw new RuntimeException('Leitor falhou: '.$reader->getErrorOutput());
    }
    $result = json_decode($reader->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    if ($result['total'] !== $oldPrice + 100 || $result['revision'] !== CatalogLock::revision()) {
        throw new RuntimeException('Cotação não refletiu preço e revisão do commit.');
    }
    echo 'PASS: leitor bloqueado durante alteração; cotação usa preço e revisão do commit.'.PHP_EOL;
} finally {
    $reader->stop();
    app(ManageCatalog::class)->save(CatalogResource::Variants, [...$input, 'price' => Money::decimal($oldPrice)], CatalogContext::console(), $variant->id);
}

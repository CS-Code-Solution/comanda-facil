<?php

// Standalone recovery drill. Both databases must already exist, owned by the app user.
use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryOrders;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\PrivateFiles;
use App\Models\CatalogProduct;
use App\Models\CatalogVariant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = config('database.connections.pgsql.database');
$target = $argv[1] ?? '';
if (! app()->environment('testing') || ! is_string($source) || ! preg_match('/^comanda_facil_pilot_[a-z0-9_]+$/', $source)
    || ! preg_match('/^comanda_facil_restore_[a-z0-9_]+$/', $target) || ! empty(config('database.connections.pgsql.url'))) {
    throw new RuntimeException('Use testing e bancos exclusivos comanda_facil_pilot_* / comanda_facil_restore_*.');
}
$root = storage_path('framework/testing/recovery-'.$source);
if (is_dir($root)) {
    throw new RuntimeException('Diretório do exercício já existe. Preserve/revise antes de repetir.');
}
PrivateFiles::directory($root);
config(['pilot.backup_key' => 'base64:'.base64_encode(random_bytes(32)), 'pilot.backup_directory' => $root.'/backups', 'pilot.restore_directory' => $root.'/restore']);
$manager = User::where('username', 'pilot.gestor')->sole();
$variant = CatalogVariant::whereHas('product', fn ($q) => $q->where('name', 'Pizza calabresa'))->where('pizza_size', 'g')->sole();
$intent = app(DeliveryOrders::class)->preview(['phone' => '86999001122', 'address' => ['recipient' => 'Pessoa QA', 'street' => 'Rua QA', 'number' => '10',
    'neighborhood' => 'Centro', 'city' => 'Cidade QA', 'state' => 'PI', 'cep' => '64000000'], 'items' => [['variant_id' => $variant->id, 'quantity' => 1]]], DeliveryContext::staff($manager));
app(DeliveryOrders::class)->confirm($intent->id, DeliveryContext::staff($manager));
$product = CatalogProduct::findOrFail($variant->product_id);
$oldPhoto = $product->photo_path;
$photo = 'catalog/photos/pilot-recovery-'.bin2hex(random_bytes(8)).'.png';
Storage::disk('local')->put($photo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$product->forceFill(['photo_path' => $photo])->save();
try {
    $service = app(DatabaseBackup::class);
    $start = microtime(true);
    $package = $service->create();
    $verified = $service->verify($root.'/backups/'.$package['file'], $target);
    $restored = $verified['workspace'].'/restored-files/'.$photo;
    if ($verified['decrypted_fields'] < 5 || hash_file('sha256', $restored) !== hash_file('sha256', Storage::disk('local')->path($photo))) {
        throw new RuntimeException('Recuperação de campos cifrados/foto falhou.');
    }
    $refused = false;
    try {
        $service->verify($root.'/backups/'.$package['file'], $target);
    } catch (RuntimeException) {
        $refused = true;
    }
    if (! $refused) {
        throw new RuntimeException('Destino preenchido não foi protegido.');
    }
    echo json_encode(['passed' => true, 'tables' => $verified['tables'], 'encrypted_fields' => $verified['decrypted_fields'],
        'photos' => $package['photos'], 'nonempty_target_refused' => true, 'seconds' => round(microtime(true) - $start, 2)], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $product->forceFill(['photo_path' => $oldPhoto])->save();
    Storage::disk('local')->delete($photo);
    DB::disconnect('pilot_restore');
    PrivateFiles::cleanup($root, storage_path('framework/testing'));
}

<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    if (! app()->environment('local')) {
        throw new RuntimeException('O lançador é exclusivo do perfil local.');
    }
    $ran = DB::table('migrations')->pluck('migration')->all();
    $expected = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);
    if (array_diff($expected, $ran) !== []) {
        throw new RuntimeException('Há migrações pendentes. Faça backup e atualize o esquema antes de iniciar o piloto.');
    }
    if (! is_file(public_path('build/manifest.json')) || is_file(public_path('hot'))) {
        throw new RuntimeException('Encerre o Vite de desenvolvimento e execute npm run build antes do piloto.');
    }
    echo 'Pré-verificação local aprovada.'.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Piloto não iniciado: confira perfil local, migrações, banco e build sem Vite ativo.'.PHP_EOL);
    exit(1);
}

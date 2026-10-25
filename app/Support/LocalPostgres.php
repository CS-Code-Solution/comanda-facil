<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class LocalPostgres
{
    public function startIfConfigured(): bool
    {
        if (! config('local-postgres.enabled')) {
            return false;
        }
        if (! app()->environment('local')) {
            throw new RuntimeException('O PostgreSQL gerenciado pelo projeto só pode iniciar em APP_ENV=local.');
        }
        $connection = config('database.connections.pgsql');
        $port = filter_var($connection['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (config('database.default') !== 'pgsql' || $connection['host'] !== '127.0.0.1' || ! empty($connection['url']) || $port === false) {
            throw new RuntimeException('O modo PostgreSQL local exige DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, porta válida e DB_URL vazio.');
        }
        $bin = config('local-postgres.bin');
        $data = config('local-postgres.data');
        if (! is_string($bin) || ! is_string($data) || $bin === '' || $data === '') {
            throw new RuntimeException('Configure LOCAL_POSTGRES_BIN e LOCAL_POSTGRES_DATA no .env.');
        }
        $control = rtrim($bin, '/\\').DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'pg_ctl.exe' : 'pg_ctl');
        if (! is_file($control) || ! is_file($data.'/PG_VERSION') || ! is_file($data.'/global/pg_control')) {
            throw new RuntimeException('Binário pg_ctl ou cluster local inexistente. Confira LOCAL_POSTGRES_BIN/DATA; nenhum banco foi criado ou alterado.');
        }
        $status = Process::timeout(10)->run([$control, 'status', '-D', $data]);
        if ($status->exitCode() === 0) {
            return true;
        }
        if ($status->exitCode() !== 3) {
            throw new RuntimeException('Não foi possível consultar o estado do cluster PostgreSQL local.');
        }
        $log = dirname($data).DIRECTORY_SEPARATOR.'comanda-facil-postgres.log';
        $started = Process::timeout(25)->run([$control, 'start', '-w', '-t', '20', '-D', $data, '-l', $log, '-o', '-h 127.0.0.1 -p '.$port]);
        if ($started->failed() && Process::timeout(10)->run([$control, 'status', '-D', $data])->exitCode() !== 0) {
            throw new RuntimeException('Não foi possível iniciar PostgreSQL local. Consulte o log: '.$log);
        }

        return true;
    }
}

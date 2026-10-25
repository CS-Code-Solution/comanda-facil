<?php

namespace App\Console\Commands;

use App\Support\LocalPostgres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareLocalDatabase extends Command
{
    protected $signature = 'dev:database';

    protected $description = 'Verifica o banco e inicia o cluster local quando configurado explicitamente';

    public function handle(LocalPostgres $postgres): int
    {
        if (! app()->environment('local')) {
            $this->error('Use somente em APP_ENV=local.');

            return self::FAILURE;
        }
        try {
            $postgres->startIfConfigured();
            DB::connection()->getPdo();
            $this->info('Banco de desenvolvimento disponível.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Banco de desenvolvimento indisponível. Confira DB_* e LOCAL_POSTGRES_* no .env, o pg_ctl e o log local.');

            return self::FAILURE;
        }
    }
}

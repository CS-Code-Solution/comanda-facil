<?php

namespace App\Console\Commands;

use App\Application\Orders\OperationsChanges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishOperations extends Command
{
    protected $signature = 'operations:publish {--watch : Continuar consultando a cada segundo}';

    protected $description = 'Publicar invalidações persistidas no Reverb e repetir falhas com backoff';

    public function handle(OperationsChanges $changes): int
    {
        do {
            $count = $changes->publish();
            if (! $this->option('watch')) {
                $pending = DB::table('operations_outbox')->whereNull('delivered_at')->count();
                $this->info("Publicados: {$count}; pendentes (incluindo backoff): {$pending}.");

                return $pending > 0 ? self::FAILURE : self::SUCCESS;
            }
            usleep(1000000);
        } while (true);
    }
}

<?php

namespace App\Console\Commands;

use App\Application\Operations\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class VerifyOperationsRestore extends Command
{
    protected $signature = 'operations:restore-test {archive} {target : Banco pré-criado vazio com prefixo comanda_facil_restore_}';

    protected $description = 'Restaurar e verificar backup em banco isolado, sem substituir dados de uso';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $result = $backup->verify((string) $this->argument('archive'), (string) $this->argument('target'));
            $this->info(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Restauração não verificada. Confira pacote/chave, destino vazio e ferramentas. Preserve o destino isolado para diagnóstico; nenhum banco de uso foi promovido.');

            return self::FAILURE;
        }
    }
}

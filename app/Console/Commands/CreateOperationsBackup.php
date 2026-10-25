<?php

namespace App\Console\Commands;

use App\Application\Operations\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class CreateOperationsBackup extends Command
{
    protected $signature = 'operations:backup';

    protected $description = 'Criar backup AES-256 de PostgreSQL, fotos e chave de aplicação, sem exibir segredos';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $result = $backup->create();
            $this->info(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup não concluído. Verifique chave de backup, permissões e ferramentas PostgreSQL. Nenhum segredo foi exibido.');

            return self::FAILURE;
        }
    }
}

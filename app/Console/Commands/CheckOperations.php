<?php

namespace App\Console\Commands;

use App\Application\Operations\OperationalStatus;
use Illuminate\Console\Command;

class CheckOperations extends Command
{
    protected $signature = 'operations:check {--production : Verificar requisitos adicionais de produção} {--json}';

    protected $description = 'Diagnóstico operacional sem expor credenciais ou dados pessoais';

    public function handle(OperationalStatus $status): int
    {
        $result = $status->read((bool) $this->option('production'));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Verificação', 'Estado', 'Ação'], array_map(fn ($row) => [$row['label'], $row['status'], $row['detail']], $result['checks']));
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}

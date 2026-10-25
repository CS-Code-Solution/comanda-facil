<?php

namespace App\Application\Operations;

use RuntimeException;
use Symfony\Component\Process\Process;

class PostgresTools
{
    /** @param list<string> $arguments */
    public function run(string $tool, array $arguments, string $database): void
    {
        $config = config('database.connections.pgsql');
        if (config('database.default') !== 'pgsql' || ! empty($config['url']) || ! in_array($tool, ['pg_dump', 'pg_restore'], true)) {
            throw new RuntimeException('Backup exige PostgreSQL com parâmetros explícitos e DB_URL vazio.');
        }
        $bin = config('pilot.postgres_bin') ?: config('local-postgres.bin');
        $executable = ($bin ? rtrim($bin, '/\\').DIRECTORY_SEPARATOR : '').$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        $process = new Process([$executable, ...$arguments], base_path(), [
            'PGHOST' => (string) $config['host'], 'PGPORT' => (string) $config['port'], 'PGUSER' => (string) $config['username'],
            'PGPASSWORD' => (string) ($config['password'] ?? ''), 'PGDATABASE' => $database,
            'PGSSLMODE' => (string) ($config['sslmode'] ?? 'prefer'), 'PGCONNECT_TIMEOUT' => '10',
        ]);
        $process->setTimeout(600);
        try {
            $process->run();
        } catch (\Throwable) {
            throw new RuntimeException('Não foi possível executar a ferramenta PostgreSQL. Verifique binários e acesso.');
        }
        if (! $process->isSuccessful()) {
            throw new RuntimeException('A ferramenta PostgreSQL recusou a operação. Verifique versão, acesso e banco de destino.');
        }
    }
}

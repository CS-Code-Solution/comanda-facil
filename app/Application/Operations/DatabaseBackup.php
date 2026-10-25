<?php

namespace App\Application\Operations;

use App\Application\Catalog\CatalogLock;
use App\Models\CatalogProduct;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackup
{
    public function __construct(private BackupArchive $archive, private PostgresTools $postgres) {}

    /** @return array<string, mixed> */
    public function create(): array
    {
        $this->archive->password();
        $root = (string) config('pilot.backup_directory');
        PrivateFiles::directory($root);
        $lock = fopen($root.'/.backup.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Já existe um backup em execução.');
        }
        PrivateFiles::file($root.'/.backup.lock');
        $id = (string) Str::uuid();
        $work = $root.'/.work-'.$id;
        PrivateFiles::directory($work);
        $part = $root.'/'.$id.'.part';
        try {
            // A session lock freezes catalog photo references without blocking orders.
            DB::select('select pg_advisory_lock_shared(?)', [CatalogLock::KEY]);
            try {
                $manifest = DB::transaction(function () use ($work, $id): array {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                    $snapshot = DB::selectOne('select pg_export_snapshot() as id')->id;
                    $database = (string) config('database.connections.pgsql.database');
                    $this->postgres->run('pg_dump', ['--format=custom', '--no-owner', '--no-privileges', '--snapshot='.$snapshot, '--file='.$work.'/database.dump'], $database);
                    PrivateFiles::file($work.'/database.dump');
                    $counts = [];
                    foreach (DB::select("select tablename from pg_tables where schemaname='public' order by tablename") as $table) {
                        $counts[$table->tablename] = DB::table($table->tablename)->count();
                    }
                    $entries = [$this->entry('database.dump', $work.'/database.dump')];
                    $paths = CatalogProduct::query()->whereNotNull('photo_path')->distinct()->pluck('photo_path');
                    foreach ($paths as $index => $path) {
                        if (! is_string($path) || ! preg_match('/^catalog\/photos\/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$/', $path)) {
                            throw new RuntimeException('Referência de foto inválida; revise o catálogo antes de criar backup.');
                        }
                        $source = Storage::disk('local')->path($path);
                        $storage = realpath(Storage::disk('local')->path(''));
                        $resolved = realpath($source);
                        if (! $storage || ! $resolved || ! str_starts_with($resolved, $storage.DIRECTORY_SEPARATOR) || is_link($source)) {
                            throw new RuntimeException('Foto ausente ou fora do armazenamento privado.');
                        }
                        $name = 'files/'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
                        $destination = $work.'/'.str_replace('/', '-', $name);
                        if (! copy($source, $destination)) {
                            throw new RuntimeException('Falha ao copiar foto para o backup.');
                        }
                        PrivateFiles::file($destination);
                        $entries[] = [...$this->entry($name, $destination), 'path' => $path];
                    }

                    return ['version' => 1, 'id' => $id, 'created_at' => now()->toIso8601String(), 'database' => $database,
                        'app_key' => config('app.key'), 'previous_keys' => config('app.previous_keys', []),
                        'cipher' => config('app.cipher'), 'tables' => $counts, 'entries' => $entries];
                });
            } finally {
                DB::select('select pg_advisory_unlock_shared(?)', [CatalogLock::KEY]);
            }
            $files = [];
            foreach ($manifest['entries'] as $entry) {
                $files[$entry['entry']] = $work.'/'.str_replace('/', '-', $entry['entry']);
            }
            $this->archive->write($part, $manifest, $files);
            $path = $root.'/'.$id.'.cfbackup';
            if (! rename($part, $path)) {
                throw new RuntimeException('Não foi possível concluir arquivo de backup.');
            }
            $result = ['id' => $id, 'file' => basename($path), 'created_at' => $manifest['created_at'], 'bytes' => filesize($path), 'photos' => count($manifest['entries']) - 1];
            $this->status($root.'/_latest.json', [...$result, 'source' => self::fingerprint()]);
            $this->audit('backup_created', $result);

            return $result;
        } finally {
            if (is_file($part)) {
                unlink($part);
            }
            PrivateFiles::cleanup($work, $root);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Restores only to a pre-created EMPTY isolated database. Never promotes it.
     * @return array<string, mixed>
     */
    public function verify(string $archive, string $target): array
    {
        if (! app()->environment(['local', 'testing']) || ! preg_match('/^comanda_facil_restore_[a-z0-9_]+$/', $target)
            || $target === config('database.connections.pgsql.database')) {
            throw new RuntimeException('Restauração de teste exige APP_ENV local/testing e banco vazio com prefixo comanda_facil_restore_, diferente do banco de uso.');
        }
        $root = (string) config('pilot.restore_directory');
        PrivateFiles::directory($root);
        $work = $root.'/'.Str::uuid();
        PrivateFiles::directory($work);
        $manifest = $this->archive->unpack($archive, $work);
        config(['database.connections.pilot_restore' => [...config('database.connections.pgsql'), 'url' => null, 'database' => $target]]);
        DB::purge('pilot_restore');
        $connection = DB::connection('pilot_restore');
        try {
            $actual = $connection->selectOne('select current_database() as name')->name;
            if ($actual !== $target || (int) $connection->selectOne("select count(*) as total from pg_tables where schemaname='public'")->total !== 0) {
                throw new RuntimeException('O destino precisa existir e estar vazio. Nenhuma tabela existente será substituída.');
            }
            $this->postgres->run('pg_restore', ['--no-owner', '--no-privileges', '--exit-on-error', '--single-transaction', '--dbname='.$target, $work.'/database.dump'], $target);
            foreach ($manifest['tables'] as $table => $count) {
                if (! is_string($table) || ! preg_match('/^[a-z][a-z0-9_]*$/', $table) || ! is_int($count) || $connection->table($table)->count() !== $count) {
                    throw new RuntimeException('Contagem de registros restaurados diverge do snapshot.');
                }
            }
            $key = $this->key($manifest['app_key']);
            $encrypter = new Encrypter($key, $manifest['cipher']);
            $encrypter->previousKeys(array_map($this->key(...), $manifest['previous_keys'] ?? []));
            $decrypted = 0;
            foreach (['dining_tables' => ['qr_token'], 'pickup_accounts' => ['phone'], 'pickup_intents' => ['phone'],
                'delivery_accounts' => ['phone', 'address'], 'delivery_intents' => ['phone', 'address']] as $table => $columns) {
                foreach ($connection->table($table)->select($columns)->cursor() as $row) {
                    foreach ($columns as $column) {
                        if ($row->$column !== null) {
                            $encrypter->decryptString($row->$column);
                            $decrypted++;
                        }
                    }
                }
            }
            foreach ($manifest['entries'] as $entry) {
                if (isset($entry['path'])) {
                    $destination = $work.'/restored-files/'.$entry['path'];
                    if (! is_dir(dirname($destination))) {
                        mkdir(dirname($destination), 0700, true);
                    }
                    if (! copy($work.'/'.str_replace('/', '-', $entry['entry']), $destination)) {
                        throw new RuntimeException('Falha ao restaurar foto.');
                    }
                    PrivateFiles::file($destination);
                }
            }
            file_put_contents($work.'/.restore.env', 'APP_KEY='.$manifest['app_key'].PHP_EOL.'APP_PREVIOUS_KEYS='.implode(',', $manifest['previous_keys'] ?? []).PHP_EOL);
            PrivateFiles::file($work.'/.restore.env');
            $result = ['id' => $manifest['id'], 'verified_at' => now()->toIso8601String(), 'target' => $target,
                'tables' => count($manifest['tables']), 'decrypted_fields' => $decrypted, 'workspace' => $work];
            $this->status($work.'/.verification.json', $result);
            $this->status((string) config('pilot.backup_directory').'/_last-restore.json', array_diff_key($result, ['workspace' => true]));
            $this->audit('restore_verified', array_diff_key($result, ['workspace' => true]));

            return $result;
        } finally {
            DB::disconnect('pilot_restore');
        }
    }

    private function key(string $key): string
    {
        if (! preg_match('/^base64:[A-Za-z0-9+\/=]+$/', $key) || ! ($bytes = base64_decode(substr($key, 7), true))) {
            throw new RuntimeException('Chave de aplicação inválida no pacote.');
        }

        return $bytes;
    }

    public static function fingerprint(): string
    {
        $connection = config('database.connections.pgsql');

        return hash('sha256', json_encode([$connection['host'], $connection['port'], $connection['database']], JSON_THROW_ON_ERROR));
    }

    /** @return array{entry: string, sha256: string, size: int} */
    private function entry(string $entry, string $path): array
    {
        $checksum = hash_file('sha256', $path);
        $size = filesize($path);
        if ($checksum === false || $size === false) {
            throw new RuntimeException('Não foi possível verificar o arquivo do backup.');
        }

        return ['entry' => $entry, 'sha256' => $checksum, 'size' => $size];
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, array $details): void
    {
        DB::table('operations_events')->insert(['action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    /** @param array<string, mixed> $data */
    private function status(string $path, array $data): void
    {
        $temporary = $path.'.'.Str::uuid().'.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Falha ao registrar estado do backup.');
        }
        PrivateFiles::file($temporary);
        if (! rename($temporary, $path)) {
            throw new RuntimeException('Falha ao concluir metadados do backup.');
        }
    }
}

<?php

namespace App\Application\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationalStatus
{
    /** @return array<string, mixed> */
    public function read(bool $production = false): array
    {
        $checks = [];
        $add = function (string $key, string $label, bool $ok, string $detail) use (&$checks): void {
            $checks[] = ['key' => $key, 'label' => $label, 'status' => $ok ? 'pass' : 'fail', 'detail' => $detail];
        };
        try {
            DB::select('select 1');
            $ran = DB::table('migrations')->pluck('migration')->all();
            $expected = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);
            $pending = count(array_diff($expected, $ran));
            $add('database', 'Banco e migrações', $pending === 0, $pending === 0 ? 'PostgreSQL disponível; esquema atualizado.' : 'Execute as migrações pendentes antes de operar.');
        } catch (Throwable) {
            return ['ready' => false, 'checks' => [['key' => 'database', 'label' => 'Banco de dados', 'status' => 'fail', 'detail' => 'Banco indisponível ou esquema não preparado.']], 'stats' => [], 'backup' => null];
        }
        if ($pending > 0) {
            return ['ready' => false, 'checks' => $checks, 'stats' => [], 'backup' => null];
        }
        $heartbeat = DB::table('operations_heartbeats')->where('name', 'publisher')->first();
        $publisher = $heartbeat && CarbonImmutable::parse($heartbeat->seen_at)->greaterThan(now()->subSeconds(config('pilot.publisher_max_age_seconds')));
        $add('publisher', 'Atualizador de pedidos', (bool) $publisher, $publisher ? 'Emissor publicou heartbeat recentemente.' : 'Inicie o emissor ou scheduler e confira Reverb/conexão.');
        $server = config('reverb.servers.reverb');
        $socket = @fsockopen($server['host'] === '0.0.0.0' ? '127.0.0.1' : $server['host'], $server['port'], timeout: 0.3);
        $add('reverb', 'Servidor de atualizações', is_resource($socket), 'Confira o processo Reverb e a conexão dos aparelhos; a sondagem TCP verifica apenas disponibilidade local.');
        if (is_resource($socket)) {
            fclose($socket);
        }
        $outbox = DB::table('operations_outbox')->whereNull('delivered_at');
        $pendingOutbox = (clone $outbox)->count();
        $oldOutbox = (clone $outbox)->where('created_at', '<', now()->subSeconds(config('pilot.outbox_max_age_seconds')))->count();
        // A failed historical copy is resolved when its replacement succeeds.
        // Preserve history while monitoring only the latest copy of the current revision.
        $currentPrints = DB::table('print_jobs as job')->join('dining_orders as orders', 'orders.id', '=', 'job.order_id')
            ->whereColumn('job.revision', 'orders.content_revision')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('print_jobs as newer')
                ->whereColumn('newer.order_id', 'job.order_id')->whereColumn('newer.revision', 'job.revision')
                ->whereColumn('newer.target', 'job.target')->whereColumn('newer.copy', '>', 'job.copy'));
        $failedPrints = (clone $currentPrints)->where('job.status', 'failed')->count();
        $stalledPrints = (clone $currentPrints)->where('job.status', 'printing')->where('job.claimed_at', '<', now()->subMinutes(2))->count();
        $add('outbox', 'Atualizações atrasadas', $oldOutbox === 0, $oldOutbox ? 'Há atualizações atrasadas. Confira emissor e Reverb.' : 'Nenhuma atualização ultrapassou o limite operacional.');
        $add('printing', 'Fila de impressão', $failedPrints + $stalledPrints === 0, $failedPrints + $stalledPrints ? 'Resolva falhas/assunções interrompidas na fila, conferindo o papel antes de reimprimir.' : 'Nenhuma falha ou assunção interrompida pendente.');
        $add('storage', 'Armazenamento', is_writable(storage_path()) && disk_free_space(storage_path()) > 536870912, 'Confirme permissão de gravação e ao menos 512 MiB livres; isto não é dimensionamento de capacidade.');
        $latest = $this->metadata('_latest.json');
        $restore = $this->metadata('_last-restore.json');
        $package = is_string($latest['file'] ?? null) && preg_match('/^[a-f0-9-]{36}\.cfbackup$/', $latest['file'])
            ? config('pilot.backup_directory').'/'.$latest['file'] : null;
        $sameSource = ($latest['source'] ?? null) === DatabaseBackup::fingerprint();
        $fresh = $latest && $sameSource && $package && is_file($package) && ! is_link($package) && filesize($package) === ($latest['bytes'] ?? null)
            && $this->recent($latest['created_at'] ?? null);
        $add('backup', 'Backup recente', (bool) $fresh, $fresh ? 'Há pacote cifrado recente.' : 'Crie backup com operations:backup e mantenha cópia externa protegida.');
        $add('restore', 'Restauração do último backup', $sameSource && $latest && $restore && ($latest['id'] ?? '') === ($restore['id'] ?? null),
            'Verifique o pacote mais recente com operations:restore-test em banco vazio isolado.');
        $keyOk = false;
        try {
            app(BackupArchive::class)->password();
            $keyOk = true;
        } catch (Throwable) {
        }
        $add('backup_key', 'Chave de backup', $keyOk, $keyOk ? 'Chave própria configurada; guarde cópia fora deste computador.' : 'Configure OPERATIONS_BACKUP_KEY separada de APP_KEY.');
        $add('retention', 'Retenção', config('pilot.purge_enabled') === false, 'Expurgo automático desativado por decisão do responsável; nenhum histórico é apagado pelo piloto.');
        $add('staff', 'Responsável ativo', DB::table('users')->where('role', 'gestor')->where('is_active', true)->whereNotNull('pin_hash')->exists(), 'É necessário um gestor individual ativo para operar configurações e conferir o piloto.');
        $failedJobs = DB::table('failed_jobs')->count();
        $add('jobs', 'Tarefas com falha', $failedJobs === 0, 'Investigue tarefas com falha antes de liberar o piloto; não apague evidências para ocultar incidentes.');
        if ($production || app()->isProduction()) {
            $secure = ! config('app.debug') && str_starts_with((string) config('app.url'), 'https://') && config('session.secure') === true
                && ! config('inertia.devtools.enabled') && config('session.http_only') === true;
            $add('configuration', 'Configuração de produção', $secure, 'Produção exige HTTPS, debug/DevTools desativados e cookies Secure/HttpOnly.');
        } else {
            $add('configuration', 'Perfil local', app()->environment(['local', 'testing']), 'Piloto local. Acesso de celulares na rede requer validar TLS/proxy/rede antes do uso real.');
        }

        return ['ready' => ! collect($checks)->contains('status', 'fail'), 'checks' => $checks,
            'stats' => ['outboxPending' => $pendingOutbox, 'outboxLate' => $oldOutbox, 'printFailed' => $failedPrints, 'printStalled' => $stalledPrints,
                'failedJobs' => $failedJobs], 'backup' => $latest ? array_intersect_key($latest, array_flip(['id', 'created_at', 'bytes', 'photos'])) : null];
    }

    private function recent(mixed $value): bool
    {
        try {
            return is_string($value) && CarbonImmutable::parse($value)->between(now()->subHours(config('pilot.backup_max_age_hours')), now()->addMinute());
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function metadata(string $file): ?array
    {
        $path = config('pilot.backup_directory').'/'.$file;
        if (! is_file($path) || filesize($path) > 1048576) {
            return null;
        }
        try {
            $text = file_get_contents($path);
            if ($text === false) {
                return null;
            }
            $value = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

            return is_array($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

namespace Tests\Feature\Console;

use App\Support\LocalPostgres;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LocalPostgresTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/pg-start-'.Str::uuid());
        File::ensureDirectoryExists($this->directory.'/data/global');
        File::put($this->directory.'/data/PG_VERSION', '18');
        File::put($this->directory.'/data/global/pg_control', 'fixture');
        File::put($this->directory.'/'.(PHP_OS_FAMILY === 'Windows' ? 'pg_ctl.exe' : 'pg_ctl'), 'fixture');
        config(['local-postgres.enabled' => true, 'local-postgres.bin' => $this->directory, 'local-postgres.data' => $this->directory.'/data',
            'database.default' => 'pgsql', 'database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 55432, 'database.connections.pgsql.url' => '']);
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        $root = realpath(storage_path('framework/testing'));
        $directory = realpath($this->directory);
        if ($root !== false && $directory !== false && str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($directory);
        }
        parent::tearDown();
    }

    public function test_disabled_mode_does_not_launch_processes(): void
    {
        Process::fake();
        config(['local-postgres.enabled' => false]);
        $this->assertFalse(app(LocalPostgres::class)->startIfConfigured());
        Process::assertNothingRan();
    }

    public function test_management_is_rejected_outside_local_environment(): void
    {
        $this->expectException(RuntimeException::class);
        app(LocalPostgres::class)->startIfConfigured();
    }

    public function test_existing_running_cluster_is_not_restarted(): void
    {
        $this->app['env'] = 'local';
        Process::fake([Process::result(exitCode: 0)]);
        $this->assertTrue(app(LocalPostgres::class)->startIfConfigured());
        Process::assertRanTimes(fn ($process) => $process->command[1] === 'status', 1);
        Process::assertNotRan(fn ($process) => $process->command[1] === 'start');
    }

    public function test_stopped_cluster_starts_with_loopback_only_and_configured_port(): void
    {
        $this->app['env'] = 'local';
        Process::fake([Process::sequence()->push(Process::result(exitCode: 3))->push(Process::result(exitCode: 0))]);
        $this->assertTrue(app(LocalPostgres::class)->startIfConfigured());
        Process::assertRan(fn ($process) => $process->command[1] === 'start' && in_array('-h 127.0.0.1 -p 55432', $process->command, true));
    }

    public function test_remote_connection_is_never_managed(): void
    {
        $this->app['env'] = 'local';
        config(['database.connections.pgsql.host' => 'db.exemplo']);
        $this->expectException(RuntimeException::class);
        app(LocalPostgres::class)->startIfConfigured();
    }

    public function test_missing_cluster_is_not_created_or_started(): void
    {
        $this->app['env'] = 'local';
        config(['local-postgres.data' => $this->directory.'/missing']);
        Process::fake();
        $this->expectException(RuntimeException::class);
        try {
            app(LocalPostgres::class)->startIfConfigured();
        } finally {
            Process::assertNothingRan();
        }
    }
}

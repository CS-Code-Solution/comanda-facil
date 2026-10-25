<?php

namespace Tests\Feature\Operations;

use App\Application\Operations\BackupArchive;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\PrivateFiles;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BackupArchiveTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/archive-'.Str::uuid());
        PrivateFiles::directory($this->directory);
        config(['pilot.backup_key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    protected function tearDown(): void
    {
        PrivateFiles::cleanup($this->directory, storage_path('framework/testing'));
        parent::tearDown();
    }

    private function package(array $extra = []): string
    {
        file_put_contents($this->directory.'/source', 'conteudo privado de fixture');
        $manifest = ['version' => 1, 'app_key' => 'base64:'.base64_encode(random_bytes(32)), 'tables' => [],
            'entries' => [['entry' => 'database.dump', 'size' => filesize($this->directory.'/source'),
                'sha256' => hash_file('sha256', $this->directory.'/source')]], ...$extra];
        app(BackupArchive::class)->write($this->directory.'/archive', $manifest, ['database.dump' => $this->directory.'/source']);
        mkdir($this->directory.'/output', 0700);

        return $this->directory.'/archive';
    }

    public function test_package_is_encrypted_and_integrity_round_trip_is_verified(): void
    {
        $archive = $this->package();
        $this->assertStringNotContainsString('conteudo privado', file_get_contents($archive));
        $manifest = app(BackupArchive::class)->unpack($archive, $this->directory.'/output');
        $this->assertSame(1, $manifest['version']);
        $this->assertSame('conteudo privado de fixture', file_get_contents($this->directory.'/output/database.dump'));
    }

    public function test_wrong_key_cannot_open_manifest(): void
    {
        $archive = $this->package();
        config(['pilot.backup_key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->expectException(RuntimeException::class);
        app(BackupArchive::class)->unpack($archive, $this->directory.'/output');
    }

    public function test_checksum_mismatch_is_rejected_before_restore(): void
    {
        $archive = $this->package(['entries' => [['entry' => 'database.dump', 'size' => 26, 'sha256' => str_repeat('0', 64)]]]);
        $this->expectException(RuntimeException::class);
        app(BackupArchive::class)->unpack($archive, $this->directory.'/output');
    }

    public function test_traversal_and_unlisted_entries_are_rejected(): void
    {
        $archive = $this->package(['entries' => [['entry' => '../outside', 'size' => 1, 'sha256' => str_repeat('0', 64)]]]);
        $this->expectException(RuntimeException::class);
        app(BackupArchive::class)->unpack($archive, $this->directory.'/output');
    }

    public function test_restore_never_targets_usage_database_or_production(): void
    {
        $this->expectException(RuntimeException::class);
        app(DatabaseBackup::class)->verify('unused', (string) config('database.connections.pgsql.database'));
    }

    public function test_backup_key_cannot_be_application_key(): void
    {
        config(['pilot.backup_key' => config('app.key')]);
        $this->expectException(RuntimeException::class);
        app(BackupArchive::class)->password();
    }
}

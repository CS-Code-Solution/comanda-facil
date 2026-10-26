<?php

namespace Tests\Feature\Operations;

use App\Application\Dining\DiningContext;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\OperationalStatus;
use App\Application\Operations\PilotChecklist;
use App\Application\Operations\PrivateFiles;
use App\Application\Orders\OrderPrinting;
use App\Application\Pickup\PickupContext;
use App\Application\Pickup\PickupOrders;
use App\Models\DiningOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CashTestCase;

class OperationalPilotTest extends CashTestCase
{
    public function test_panel_and_checklist_are_manager_only(): void
    {
        $this->signIn($this->manager)->get(route('pilot.index'))->assertInertia(fn (Assert $p) => $p->component('operations/health')->has('checks', 6));
        $this->signIn($this->cashier)->get(route('pilot.index'))->assertForbidden();
        $this->post(route('pilot.check', 'thermal_print'), ['version' => 1, 'passed' => true, 'performed' => true,
            'evidence' => 'Evidência sintética de teste'])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(PilotChecklist::class)->record('thermal_print', ['version' => 1, 'passed' => true, 'performed' => true,
            'evidence' => 'Evidência sintética de teste'], DiningContext::staff($this->cashier));
    }

    public function test_manual_evidence_requires_declaration_and_cannot_accept_pending_pilot(): void
    {
        $this->signIn($this->manager)->post(route('pilot.check', 'thermal_print'), [
            'version' => 1, 'passed' => true, 'evidence' => 'Fixture sem teste físico'])->assertSessionHasErrors('performed');
        $this->post(route('pilot.check', 'restaurant_acceptance'), [
            'version' => 1, 'passed' => true, 'performed' => true, 'evidence' => 'Tentativa de aceite em fixture incompleta'])->assertSessionHasErrors('pilot');
        $this->assertDatabaseHas('pilot_checks', ['name' => 'restaurant_acceptance', 'passed' => false]);
    }

    public function test_evidence_is_versioned_audited_and_change_invalidates_acceptance(): void
    {
        DB::table('pilot_checks')->where('name', '<>', 'restaurant_acceptance')->update(['passed' => true]);
        $this->mock(OperationalStatus::class)->shouldReceive('read')->once()->andReturn(['ready' => true]);
        $service = app(PilotChecklist::class);
        $context = DiningContext::staff($this->manager);
        $service->record('restaurant_acceptance', ['version' => 1, 'passed' => true, 'performed' => true, 'evidence' => 'Aceite sintético restrito ao teste'], $context);
        $service->record('thermal_print', ['version' => 1, 'passed' => false, 'performed' => true, 'evidence' => 'Falha sintética registrada no teste'], $context);
        $this->assertDatabaseHas('pilot_checks', ['name' => 'restaurant_acceptance', 'passed' => false, 'version' => 3]);
        $this->assertDatabaseCount('operations_events', 2);
    }

    public function test_requests_have_safe_headers_and_logs_never_contain_contact_or_payload(): void
    {
        $response = $this->get(route('home'));
        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $response->headers->get('X-Request-ID'));
        Log::spy();
        app(PickupOrders::class)->service(true, 'Teste de correlação', PickupContext::staff($this->manager));
        $context = PickupContext::staff($this->cashier);
        $intent = app(PickupOrders::class)->preview(['phone' => '86999001122', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]]], $context);
        $result = $this->signIn($this->cashier)->post(route('pickup.confirm', $intent->id));
        $order = DiningOrder::sole();
        $id = $result->headers->get('X-Request-ID');
        $this->assertDatabaseHas('order_history', ['order_id' => $order->id, 'correlation_id' => $id]);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $details) => $message === 'operation.http'
            && $details['route'] === 'pickup.confirm' && ! str_contains(json_encode($details), '999001122'));
    }

    public function test_public_readiness_has_no_internal_details_and_expurgo_is_disabled(): void
    {
        $this->getJson(route('readiness'))->assertOk()->assertExactJson(['ready' => true]);
        $this->assertFalse(config('pilot.purge_enabled'));
        $this->assertFalse(app(OperationalStatus::class)->read()['ready']);
        $this->assertDatabaseHas('pilot_checks', ['name' => 'thermal_print', 'passed' => false]);
    }

    public function test_fixture_command_refuses_shared_test_database_without_writes(): void
    {
        $before = DB::table('users')->count();
        $this->artisan('pilot:fixture')->assertExitCode(1);
        $this->assertDatabaseCount('users', $before);
    }

    public function test_diagnostics_tolerate_invalid_metadata_and_require_the_actual_package(): void
    {
        $directory = storage_path('framework/testing/status-'.Str::uuid());
        PrivateFiles::directory($directory);
        config(['pilot.backup_directory' => $directory]);
        try {
            file_put_contents($directory.'/_latest.json', json_encode(['id' => (string) Str::uuid(), 'created_at' => 'invalid date']));
            $checks = collect(app(OperationalStatus::class)->read()['checks'])->keyBy('key');
            $this->assertSame('fail', $checks['backup']['status']);
            file_put_contents($directory.'/_latest.json', json_encode(['id' => (string) Str::uuid(), 'created_at' => now()->toIso8601String(),
                'bytes' => 100, 'file' => Str::uuid().'.cfbackup']));
            $checks = collect(app(OperationalStatus::class)->read()['checks'])->keyBy('key');
            $this->assertSame('fail', $checks['backup']['status']);
            DB::table('operations_outbox')->insert(['id' => (string) Str::uuid(), 'available_at' => now(), 'created_at' => now()->subMinutes(2)]);
            $checks = collect(app(OperationalStatus::class)->read()['checks'])->keyBy('key');
            $this->assertSame('fail', $checks['outbox']['status']);
        } finally {
            PrivateFiles::cleanup($directory, storage_path('framework/testing'));
        }
    }

    public function test_downgrade_refuses_to_erase_recorded_correlation(): void
    {
        $this->order();
        $migration = require database_path('migrations/2026_10_25_000012_add_operation_correlation.php');
        try {
            $migration->down();
            $this->fail('Correlação existente precisa ser preservada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('correlação', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('order_history', 'correlation_id'));
    }

    public function test_successful_reprint_resolves_diagnostic_without_erasing_failed_copy(): void
    {
        $order = $this->order();
        $job = DB::table('print_jobs')->where('order_id', $order->id)->where('target', 'cashier')->sole();
        $printing = app(OrderPrinting::class);
        $context = DiningContext::staff($this->manager);
        $printing->act($job->id, 'claim', [], $context);
        $printing->act($job->id, 'failed', ['reason' => 'Papel ausente no teste'], $context);
        $this->assertSame(1, app(OperationalStatus::class)->read()['stats']['printFailed']);
        $copy = $printing->act($job->id, 'reprint', ['operation_id' => (string) Str::uuid(), 'reason' => 'Papel reposto no teste'], $context);
        $printing->act($copy, 'claim', [], $context);
        $printing->act($copy, 'printed', [], $context);
        $this->assertSame(0, app(OperationalStatus::class)->read()['stats']['printFailed']);
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'failed']);
        $this->assertDatabaseHas('print_events', ['job_id' => $job->id, 'action' => 'failed']);
    }

    public function test_backup_from_another_database_cannot_approve_current_diagnostics(): void
    {
        $directory = storage_path('framework/testing/status-'.Str::uuid());
        PrivateFiles::directory($directory);
        config(['pilot.backup_directory' => $directory]);
        $id = (string) Str::uuid();
        $metadata = ['id' => $id, 'file' => $id.'.cfbackup', 'created_at' => now()->toIso8601String(), 'bytes' => 1, 'source' => 'different-database'];
        try {
            file_put_contents($directory.'/'.$metadata['file'], 'x');
            file_put_contents($directory.'/_latest.json', json_encode($metadata));
            file_put_contents($directory.'/_last-restore.json', json_encode(['id' => $id]));
            $checks = collect(app(OperationalStatus::class)->read()['checks'])->keyBy('key');
            $this->assertSame('fail', $checks['backup']['status']);
            $this->assertSame('fail', $checks['restore']['status']);
            file_put_contents($directory.'/_latest.json', json_encode([...$metadata, 'source' => DatabaseBackup::fingerprint()]));
            $checks = collect(app(OperationalStatus::class)->read()['checks'])->keyBy('key');
            $this->assertSame('pass', $checks['backup']['status']);
            $this->assertSame('pass', $checks['restore']['status']);
        } finally {
            PrivateFiles::cleanup($directory, storage_path('framework/testing'));
        }
    }
}

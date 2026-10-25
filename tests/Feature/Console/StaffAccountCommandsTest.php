<?php

namespace Tests\Feature\Console;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffAccountCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_command_provisions_hashed_pin_and_audit_without_email(): void
    {
        $this->artisan('staff:create', ['username' => ' SALA.01 ', '--name' => 'Equipe Sala', '--role' => 'garcom'])
            ->expectsQuestion('PIN (6 dígitos)', '012345')
            ->expectsQuestion('Confirme o PIN', '012345')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('sala.01', $user->username);
        $this->assertNull($user->email);
        $this->assertTrue($user->isActiveStaff());
        $this->assertNotSame('012345', $user->pin_hash);
        $this->assertTrue(Hash::check('012345', $user->pin_hash));
        $this->assertArrayNotHasKey('pin_hash', $user->toArray());
        $this->assertDatabaseHas('staff_access_events', ['user_id' => $user->id, 'action' => 'created', 'source' => 'console']);
        $this->assertStringNotContainsString('012345', json_encode(DB::table('staff_access_events')->get(), JSON_THROW_ON_ERROR));
    }

    public function test_noninteractive_creation_refuses_to_collect_pin(): void
    {
        $this->artisan('staff:create', ['username' => 'sala.01', '--name' => 'Equipe Sala', '--role' => 'garcom', '--no-interaction' => true])
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mismatched_pin_does_not_create_partial_account_or_audit(): void
    {
        $this->artisan('staff:create', ['username' => 'sala.01', '--name' => 'Equipe Sala', '--role' => 'garcom'])
            ->expectsQuestion('PIN (6 dígitos)', '012345')
            ->expectsQuestion('Confirme o PIN', '012346')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_duplicate_normalized_username_is_rejected(): void
    {
        $accounts = app(ManageStaffAccount::class);
        $accounts->create('Equipe Sala', 'sala.01', StaffRole::Waiter, '012345', '012345', StaffAccessContext::console());

        try {
            $accounts->create('Outra Pessoa', ' SALA.01 ', StaffRole::Manager, '765432', '765432', StaffAccessContext::console());
            $this->fail('Nome de usuário duplicado foi aceito.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('username', $exception->errors());
        }

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_access_events', 1);
    }

    public function test_invalid_pin_format_is_rejected_by_application_use_case(): void
    {
        $accounts = app(ManageStaffAccount::class);

        foreach (['12345', '1234567', 'abcdef', ' 123456', '１２３４５６'] as $pin) {
            try {
                $accounts->create('Equipe Sala', 'sala.01', StaffRole::Waiter, $pin, $pin, StaffAccessContext::console());
                $this->fail('PIN inválido foi aceito.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('pin', $exception->errors());
            }
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_reset_command_changes_pin_increments_version_and_audits(): void
    {
        $user = User::factory()->create();

        $this->artisan('staff:reset-pin', ['username' => $user->username])
            ->expectsQuestion('Novo PIN (6 dígitos)', '765432')
            ->expectsQuestion('Confirme o PIN', '765432')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('765432', $user->refresh()->pin_hash));
        $this->assertSame(2, $user->auth_version);
        $this->assertDatabaseHas('staff_access_events', ['user_id' => $user->id, 'action' => 'pin_reset']);
    }

    public function test_disable_command_is_confirmed_and_idempotent(): void
    {
        $user = User::factory()->create();

        $this->artisan('staff:disable', ['username' => $user->username])
            ->expectsConfirmation('Desativar a conta '.$user->username.'?', 'yes')
            ->assertSuccessful();
        $this->artisan('staff:disable', ['username' => $user->username, '--force' => true])->assertSuccessful();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(2, $user->auth_version);
        $this->assertDatabaseCount('staff_access_events', 1);
        $this->assertDatabaseHas('staff_access_events', ['user_id' => $user->id, 'action' => 'disabled']);
    }

    public function test_database_seeder_does_not_create_demo_credentials(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
    }
}

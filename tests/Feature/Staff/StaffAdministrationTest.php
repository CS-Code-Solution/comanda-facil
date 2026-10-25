<?php

namespace Tests\Feature\Staff;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Enums\StaffRole;
use App\Models\StaffAccessEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => StaffRole::Manager->value]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Pessoa da Equipe', 'username' => 'nova.pessoa', 'role' => 'caixa',
            'pin' => '654321', 'pin_confirmation' => '654321', 'current_pin' => '012345',
        ];
    }

    public function test_manager_can_create_account_with_actor_audit_and_no_secret_props(): void
    {
        $manager = $this->manager();
        $this->signIn($manager)->post(route('staff.store'), [
            ...$this->payload(), 'is_active' => false, 'auth_version' => 999, 'actor_id' => 999,
            'pin_hash' => 'unsafe-input', 'source' => 'console',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $account = User::query()->where('username', 'nova.pessoa')->sole();
        $this->assertTrue($account->is_active);
        $this->assertSame(1, $account->auth_version);
        $this->assertTrue(Hash::check('654321', $account->pin_hash));
        $event = StaffAccessEvent::query()->sole();
        $this->assertDatabaseHas('staff_access_events', ['actor_id' => $manager->id, 'user_id' => $account->id, 'source' => 'web', 'action' => 'created']);
        $this->assertEqualsCanonicalizing(['name', 'username', 'role', 'is_active'], array_keys($event->after));
        $this->assertStringNotContainsString('654321', json_encode($event->after, JSON_THROW_ON_ERROR));
        $this->get(route('staff.edit', $account))->assertInertia(fn (Assert $page) => $page
            ->component('staff/edit')->where('events.0.actor', $manager->name)
            ->missing('account.pin_hash')->missing('account.password')->missing('account.auth_version'));
    }

    public function test_manager_can_list_search_and_paginate_without_credential_leaks(): void
    {
        $manager = $this->manager();
        User::factory()->count(16)->create();
        $target = User::factory()->create(['name' => 'Busca Exclusiva', 'username' => 'busca.exclusiva']);

        $this->signIn($manager)->get(route('staff.index'))->assertInertia(fn (Assert $page) => $page
            ->component('staff/index')->has('accounts.data', 15)->where('accounts.total', 18)
            ->missing('accounts.data.0.pin_hash')->missing('accounts.data.0.auth_version'));
        $this->get(route('staff.index', ['q' => 'BUSCA.EXCLUSIVA']))->assertInertia(fn (Assert $page) => $page
            ->has('accounts.data', 1)->where('accounts.data.0.id', $target->id));
    }

    public function test_every_nonmanager_role_is_denied_all_staff_management_operations(): void
    {
        $target = User::factory()->create();

        foreach ([StaffRole::Cashier, StaffRole::Waiter, StaffRole::Kitchen, StaffRole::Courier] as $role) {
            $actor = User::factory()->create(['role' => $role->value]);
            $this->signIn($actor)->get(route('staff.index'))->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page->component('errors/forbidden'));
            $this->get(route('staff.create'))->assertForbidden();
            $this->get(route('staff.edit', $target))->assertForbidden();
            $this->post(route('staff.store'), $this->payload())->assertForbidden();
            $this->put(route('staff.update', $target), $this->payload())->assertForbidden();

            foreach (['reset-pin', 'activate', 'disable', 'revoke-sessions'] as $action) {
                $this->post(route('staff.'.$action, $target), $this->payload())->assertForbidden();
            }
        }

        $this->assertDatabaseCount('staff_access_events', 0);
        $this->assertSame(1, $target->refresh()->auth_version);
    }

    public function test_wrong_current_pin_and_invalid_role_leave_no_partial_effect(): void
    {
        $this->signIn($this->manager())->post(route('staff.store'), [...$this->payload(), 'current_pin' => '999999'])
            ->assertSessionHasErrors('current_pin')->assertSessionMissing('_old_input.current_pin')->assertSessionMissing('_old_input.pin');
        $this->post(route('staff.store'), [...$this->payload(), 'role' => 'superadmin'])->assertSessionHasErrors('role');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_use_case_denies_nonmanager_even_without_http_middleware(): void
    {
        $actor = User::factory()->create();

        try {
            app(ManageStaffAccount::class)->create('Pessoa', 'outra.pessoa', StaffRole::Manager, '654321', '654321', StaffAccessContext::web($actor, '012345'));
            $this->fail('Caso de uso aceitou ator sem permissão.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('staff_access_events', 0);
        }
    }

    public function test_revoked_context_is_denied_inside_use_case(): void
    {
        $actor = $this->manager();
        $context = StaffAccessContext::web($actor, '012345');
        app(ManageStaffAccount::class)->revokeSessions($actor, StaffAccessContext::console());

        $this->expectException(AuthorizationException::class);
        app(ManageStaffAccount::class)->create('Pessoa', 'outra.pessoa', StaffRole::Waiter, '654321', '654321', $context);
    }

    public function test_update_role_and_username_audits_changes_and_revokes_previous_session(): void
    {
        $actor = $this->manager();
        $target = User::factory()->create();
        $this->signIn($actor)->put(route('staff.update', $target), [
            'name' => 'Nome Corrigido', 'username' => 'cozinha.01', 'role' => 'cozinha', 'current_pin' => '012345',
        ])->assertSessionHasNoErrors()->assertRedirect(route('staff.edit', $target));

        $event = StaffAccessEvent::query()->sole();
        $this->assertSame('garcom', $event->before['role']);
        $this->assertSame('cozinha', $event->after['role']);
        $this->assertSame('role_changed', $event->action);
        $this->assertSame(2, $target->fresh()->auth_version);
        $this->signIn($target)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_manager_can_disable_reactivate_reset_pin_and_revoke_sessions(): void
    {
        $actor = $this->manager();
        $target = User::factory()->create();
        $this->signIn($actor);

        $this->post(route('staff.disable', $target), ['current_pin' => '012345'])->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_active);
        $this->post(route('staff.reset-pin', $target), $this->payload())->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue(Hash::check('654321', $target->fresh()->pin_hash));
        $this->post(route('staff.activate', $target), ['current_pin' => '012345'])->assertSessionHasNoErrors();
        $this->assertTrue($target->fresh()->is_active);
        $this->post(route('staff.revoke-sessions', $target), ['current_pin' => '012345'])->assertSessionHasNoErrors();
        $this->assertSame(5, $target->fresh()->auth_version);
        $this->assertDatabaseCount('staff_access_events', 4);
    }

    public function test_incomplete_account_cannot_be_activated(): void
    {
        $target = User::create(['name' => 'Conta anterior', 'password' => 'unusable']);
        $this->signIn($this->manager())->post(route('staff.activate', $target), ['current_pin' => '012345'])
            ->assertSessionHasErrors('account');
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_last_manager_is_protected_including_console(): void
    {
        $manager = $this->manager();

        $this->artisan('staff:disable', ['username' => $manager->username, '--force' => true])->assertFailed();
        $this->assertTrue($manager->fresh()->is_active);

        try {
            app(ManageStaffAccount::class)->update($manager, $manager->name, $manager->username, StaffRole::Waiter, StaffAccessContext::console());
            $this->fail('Último gestor foi rebaixado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }

        $this->assertSame('gestor', $manager->fresh()->role);
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_manager_cannot_disable_or_demote_own_account_in_panel(): void
    {
        $manager = $this->manager();
        $this->manager();
        $this->signIn($manager)->post(route('staff.disable', $manager), ['current_pin' => '012345'])
            ->assertSessionHasErrors('account');
        $this->put(route('staff.update', $manager), [
            'name' => $manager->name, 'username' => $manager->username, 'role' => 'caixa', 'current_pin' => '012345',
        ])->assertSessionHasErrors('role');
        $this->assertTrue($manager->fresh()->is_active);
        $this->assertSame('gestor', $manager->fresh()->role);
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_manager_can_revoke_own_other_sessions_without_losing_current_session(): void
    {
        $manager = $this->manager();
        $this->signIn($manager)->post(route('staff.revoke-sessions', $manager), ['current_pin' => '012345'])
            ->assertSessionHas('staff.auth_version', 2);
        $this->get(route('staff.index'))->assertOk();
        $this->assertAuthenticatedAs($manager);
    }

    public function test_each_staff_member_can_change_own_pin_but_cannot_target_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->signIn($user)->put(route('pin.update'), [
            'current_pin' => '012345', 'pin' => '654321', 'pin_confirmation' => '654321', 'user_id' => $other->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('staff.auth_version', 2);
        $this->assertTrue(Hash::check('654321', $user->fresh()->pin_hash));
        $this->assertTrue(Hash::check('012345', $other->fresh()->pin_hash));
        $this->assertDatabaseHas('staff_access_events', ['user_id' => $user->id, 'actor_id' => $user->id, 'action' => 'pin_changed']);
        $this->get(route('dashboard'))->assertOk();
        $this->withSession(['staff.auth_version' => 1])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_current_pin_cannot_change_own_pin(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->put(route('pin.update'), [
            'current_pin' => '999999', 'pin' => '654321', 'pin_confirmation' => '654321',
        ])->assertSessionHasErrors('current_pin')->assertSessionMissing('_old_input.current_pin');
        $this->assertTrue(Hash::check('012345', $user->fresh()->pin_hash));
        $this->assertDatabaseCount('staff_access_events', 0);
    }

    public function test_sensitive_action_attempts_are_limited_per_actor_across_ips(): void
    {
        $this->signIn($this->manager());

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->post(route('staff.store'), [
                ...$this->payload(), 'current_pin' => '999999',
            ])->assertSessionHasErrors('current_pin');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->post(route('staff.store'), $this->payload())->assertTooManyRequests();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_access_events', 0);
    }
}

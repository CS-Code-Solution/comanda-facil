<?php

namespace Tests\Feature\Auth;

use App\Application\Staff\ManageStaffAccount;
use App\Application\Staff\StaffAccessContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_credential_and_public_registration_routes_are_closed(): void
    {
        foreach (['/register', '/forgot-password', '/reset-password/token', '/user/confirm-password', '/two-factor-challenge', '/passkeys/login/options', '/email/verify', '/.well-known/passkey-endpoints'] as $path) {
            $this->get($path)->assertNotFound();
        }

        foreach (['/register', '/forgot-password', '/reset-password', '/passkeys/login', '/two-factor-challenge', '/user/confirm-password'] as $path) {
            $this->post($path, [])->assertNotFound();
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_account_is_closed_by_default_until_provisioned(): void
    {
        $user = User::create(['name' => 'Equipe', 'password' => 'unusable-password'])->refresh();

        $this->assertFalse($user->is_active);
        $this->assertNull($user->role);
        $this->assertNull($user->username);
        $this->assertNull($user->pin_hash);
        $this->assertFalse($user->isActiveStaff());
    }

    public function test_inactive_and_unrecognized_role_accounts_cannot_log_in(): void
    {
        foreach ([['is_active' => false], ['role' => null], ['role' => 'desconhecido']] as $state) {
            $user = User::factory()->create($state);

            $this->post(route('login.store'), ['username' => $user->username, 'pin' => '012345'])
                ->assertSessionHasErrors(['username' => 'Usuário ou PIN inválido.']);
            $this->assertGuest();
        }
    }

    public function test_deactivation_revokes_existing_session_from_database_state(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        app(ManageStaffAccount::class)->disable($user, StaffAccessContext::console());

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_removing_role_revokes_existing_session(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        User::query()->whereKey($user->id)->update(['role' => null]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pin_reset_revokes_session_and_replaces_credential(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        app(ManageStaffAccount::class)->resetPin($user, '765432', '765432', StaffAccessContext::console());

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => $user->username, 'pin' => '012345'])
            ->assertSessionHasErrors('username');
        $this->post(route('login.store'), ['username' => $user->username, 'pin' => '765432'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_idle_session_expires(): void
    {
        $this->signIn(User::factory()->create());
        $this->travel(31)->minutes();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_absolute_timeout_applies_even_with_recent_activity(): void
    {
        $this->signIn(User::factory()->create())->withSession([
            'staff.started_at' => now()->subHours(13)->timestamp,
        ]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_active_request_updates_idle_timestamp(): void
    {
        $this->signIn(User::factory()->create());
        $this->travel(10)->minutes();

        $this->get(route('dashboard'))->assertOk()
            ->assertSessionHas('staff.last_activity_at', now()->timestamp);
    }

    public function test_legacy_session_without_staff_context_is_revoked(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_revoked_json_request_receives_unauthorized_response(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        app(ManageStaffAccount::class)->disable($user, StaffAccessContext::console());

        $this->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_account_page_does_not_expose_credentials_or_allow_self_privilege_changes(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.user.username', $user->username)
                ->missing('auth.user.pin_hash')
                ->missing('auth.user.password')
                ->missing('auth.user.auth_version'));

        $this->patch('/settings/profile', ['role' => 'gestor', 'is_active' => true])->assertMethodNotAllowed();
        $this->delete('/settings/profile')->assertMethodNotAllowed();
        $this->put('/settings/password', ['password' => 'new-password'])->assertNotFound();
        $this->assertSame('garcom', $user->refresh()->role);
    }

    public function test_private_user_channel_rejects_other_account_and_revoked_session(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // A suíte inicia com broadcaster null; registre os canais no driver Reverb deste teste.
        require base_path('routes/channels.php');

        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ])->assertForbidden();

        app(ManageStaffAccount::class)->disable($user, StaffAccessContext::console());
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Inertia\DevTools\DevTools;
use Inertia\DevTools\IncomingEntryBuilder;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_exposes_only_pin_form_configuration(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('pinLength', 6)
            ->missing('canResetPassword'));
    }

    public function test_staff_can_log_in_with_normalized_username_and_leading_zero_pin(): void
    {
        $user = User::factory()->create(['username' => 'equipe.sala']);
        $this->get(route('login'));
        $oldId = session()->getId();

        $response = $this->post(route('login.store'), [
            'username' => ' EQUIPE.SALA ',
            'pin' => '012345',
            'remember' => true,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('staff.auth_version', 1)
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
    }

    public function test_wrong_pin_is_rejected_without_flashing_or_emitting_pin(): void
    {
        Event::fake([Failed::class]);
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'pin' => '999999',
        ])->assertSessionHasErrors(['username' => 'Usuário ou PIN inválido.'])
            ->assertSessionMissing('_old_input.pin');

        $this->assertGuest();
        Event::assertDispatched(Failed::class, fn (Failed $event) => $event->credentials === ['username' => $user->username]);
    }

    public function test_unknown_account_receives_same_generic_error(): void
    {
        $this->post(route('login.store'), [
            'username' => 'inexistente',
            'pin' => '012345',
        ])->assertSessionHasErrors(['username' => 'Usuário ou PIN inválido.']);
        $this->assertGuest();
    }

    public function test_password_credentials_do_not_authenticate_staff(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['username', 'pin']);
        $this->assertGuest();
    }

    public function test_invalid_pin_format_is_not_flushed_to_old_input(): void
    {
        $this->post(route('login.store'), [
            'username' => 'equipe.sala',
            'pin' => '12345x',
        ])->assertSessionHasErrors('pin')->assertSessionMissing('_old_input.pin');
        $this->assertGuest();
    }

    public function test_malformed_username_is_validation_error_instead_of_server_error(): void
    {
        $this->postJson(route('login.store'), [
            'username' => ['invalid'],
            'pin' => '012345',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_account_limit_is_shared_across_ip_addresses(): void
    {
        $user = User::factory()->create(['username' => 'limitado']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->post(route('login.store'), ['username' => ' LIMITADO ', 'pin' => '999999'])
                ->assertSessionHasErrors('username');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('login.store'), ['username' => $user->username, 'pin' => '012345'])
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_ip_limit_applies_when_attempts_rotate_account_names(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->postJson(route('login.store'), [
                'username' => 'conta'.$attempt,
                'pin' => '999999',
            ])->assertUnprocessable();
        }

        $this->postJson(route('login.store'), [
            'username' => 'outra.conta',
            'pin' => '999999',
        ])->assertTooManyRequests();
    }

    public function test_logout_invalidates_staff_session(): void
    {
        $this->signIn(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('home'))->assertSessionMissing('staff');
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_optional_devtools_excludes_login_and_redacts_pin_fields(): void
    {
        config(['inertia.devtools.enabled' => true]);
        $request = Request::create('/login', 'POST', [
            'pin' => '012345',
            'pin_confirmation' => '012345',
            'pin_hash' => 'sensitive-test-hash',
        ]);
        $request->headers->set('X-Inertia', 'true');

        $this->assertFalse(DevTools::enabledForRequest($request));
        $entry = app(IncomingEntryBuilder::class)->build($request, new Response, 'test-entry', null, false);
        $encoded = json_encode($entry->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('012345', $encoded);
        $this->assertStringNotContainsString('sensitive-test-hash', $encoded);
    }
}

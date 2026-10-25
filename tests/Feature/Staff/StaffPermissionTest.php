<?php

namespace Tests\Feature\Staff;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_roles_only_access_their_allowed_entries(): void
    {
        $expected = [
            'gestor' => ['administration', 'cashier', 'dining-room', 'kitchen', 'delivery'],
            'caixa' => ['cashier', 'dining-room', 'delivery'], 'garcom' => ['dining-room', 'delivery'],
            'cozinha' => ['kitchen'], 'entregador' => ['delivery'],
        ];

        foreach (StaffRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role->value]);
            $this->signIn($user);

            foreach (['administration', 'cashier', 'dining-room', 'kitchen', 'delivery'] as $workspace) {
                $response = $this->get(route('workspace.'.$workspace));
                in_array($workspace, $expected[$role->value], true) ? $response->assertOk() : $response->assertForbidden();
            }

            $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')->has('navigation', $role === StaffRole::Manager ? 10 : ($role === StaffRole::Cashier ? 5 : ($role === StaffRole::Waiter ? 4 : 2))));
        }
    }

    public function test_role_change_is_reflected_after_new_authentication(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->get(route('workspace.kitchen'))->assertForbidden();
        $user->forceFill(['role' => 'cozinha', 'auth_version' => 2])->save();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['username' => $user->username, 'pin' => '012345'])->assertRedirect();
        $this->get(route('workspace.kitchen'))->assertOk();
        $this->get(route('workspace.dining-room'))->assertForbidden();
    }

    public function test_logout_then_switching_user_does_not_inherit_permissions(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $kitchen = User::factory()->create(['role' => 'cozinha']);
        $this->signIn($manager)->get(route('staff.index'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), ['username' => $kitchen->username, 'pin' => '012345'])->assertRedirect();
        $this->assertAuthenticatedAs($kitchen);
        $this->get(route('staff.index'))->assertForbidden();
        $this->get(route('workspace.kitchen'))->assertOk();
    }
}

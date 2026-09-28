<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_user_management(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin))
            ->get(route('admin.users'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users')
                ->has('roles', count(Role::cases()))
                ->where('auth.can.manageUsers', true),
            );

        $this->actingAs($this->userWithRole(Role::Manager))->get(route('admin.users'))->assertForbidden();
    }

    public function test_the_user_list_can_be_searched(): void
    {
        $this->userWithRole(Role::Viewer, ['name' => 'Grace Hopper']);
        $this->userWithRole(Role::Viewer, ['name' => 'Alan Turing']);

        $this->actingAs($this->userWithRole(Role::Admin))
            ->get(route('admin.users', ['search' => 'Grace']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Grace Hopper')
                ->where('users.data.0.role', Role::Viewer->value),
            );
    }

    public function test_admins_can_change_a_users_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $user = $this->userWithRole(Role::Viewer);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $user), ['role' => Role::Sales->value])
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "{$user->name} is now Sales."]);

        $this->assertTrue($user->fresh()->hasRole(Role::Sales->value));
        $this->assertFalse($user->fresh()->hasRole(Role::Viewer->value));
    }

    public function test_unknown_roles_are_rejected(): void
    {
        $user = $this->userWithRole(Role::Viewer);

        $this->actingAs($this->userWithRole(Role::Admin))
            ->patch(route('admin.users.role', $user), ['role' => 'superuser'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($user->fresh()->hasRole(Role::Viewer->value));
    }

    public function test_admins_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => Role::Viewer->value])
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'You cannot remove your own admin role.']);

        $this->assertTrue($admin->fresh()->hasRole(Role::Admin->value));
    }

    public function test_non_admins_cannot_change_roles(): void
    {
        $manager = $this->userWithRole(Role::Manager);

        $this->actingAs($manager)
            ->patch(route('admin.users.role', $manager), ['role' => Role::Admin->value])
            ->assertForbidden();

        $this->assertFalse($manager->fresh()->hasRole(Role::Admin->value));
    }

    public function test_new_registrations_get_the_default_role(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertTrue(User::firstWhere('email', 'test@example.com')->hasRole(config('crm.default_role')));
    }
}

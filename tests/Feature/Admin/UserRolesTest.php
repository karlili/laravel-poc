<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_user_management(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin))->get(route('admin.users'))->assertOk();
        $this->actingAs($this->userWithRole(Role::Manager))->get(route('admin.users'))->assertForbidden();
    }

    public function test_admins_can_change_a_users_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $user = $this->userWithRole(Role::Viewer);

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('setRole', $user->id, Role::Sales->value);

        $this->assertTrue($user->fresh()->hasRole(Role::Sales->value));
        $this->assertFalse($user->fresh()->hasRole(Role::Viewer->value));
    }

    public function test_admins_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        Livewire::actingAs($admin)
            ->test('pages::admin.users')
            ->call('setRole', $admin->id, Role::Viewer->value);

        $this->assertTrue($admin->fresh()->hasRole(Role::Admin->value));
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

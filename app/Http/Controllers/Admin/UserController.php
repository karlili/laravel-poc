<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User role management. Routes are behind the "users.manage" ability.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => collect(Role::cases())->map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $role = Role::from($request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ])['role']);

        if ($user->is($request->user()) && $role !== Role::Admin) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot remove your own admin role.')]);

            return back();
        }

        $user->syncRoles([$role->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now :role.', ['name' => $user->name, 'role' => $role->label()])]);

        return back();
    }
}

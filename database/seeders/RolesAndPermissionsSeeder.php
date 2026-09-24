<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Enums\Role as RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the CRM roles and permissions. Safe to run on every deploy.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, 'web')->syncPermissions($roleName->permissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

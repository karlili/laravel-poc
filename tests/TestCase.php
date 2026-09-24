<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles and permissions whenever a test refreshes the database.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render pages but don't need compiled frontend assets.
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Create a user with the given role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole($role->value);
    }
}

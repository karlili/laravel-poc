<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with roles and demo data.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ])->assignRole(Role::Admin->value);

        $sales = User::factory()->create([
            'name' => 'Sales User',
            'email' => 'sales@example.com',
        ])->assignRole(Role::Sales->value);

        User::factory()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
        ])->assignRole(Role::Viewer->value);

        Company::factory(12)
            ->sequence(['owner_id' => $admin->id], ['owner_id' => $sales->id])
            ->create()
            ->each(function (Company $company) use ($sales) {
                Contact::factory(3)->for($company)->create(['owner_id' => $company->owner_id]);
                Note::factory(2)->for($company, 'notable')->create(['author_id' => $sales->id]);
            });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds data every environment needs. Run on each deploy; never creates demo data.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}

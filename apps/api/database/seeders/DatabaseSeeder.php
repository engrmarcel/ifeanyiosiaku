<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed data required in every environment. Settings fall back to config('platform.defaults'),
     * so no settings rows are needed (the announcement popup is OFF by default).
     * Development-only demo data lives in DevelopmentSeeder and refuses to run in production.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);
            $this->call(InitialContentSeeder::class);
        }
    }
}

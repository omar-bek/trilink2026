<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            // UNSPSC top-level segments seeded BEFORE the comprehensive
            // seeder so the latter (which creates demo categories) can
            // optionally point at the standard taxonomy.
            UnspscSegmentsSeeder::class,
            ComprehensiveSeeder::class,
        ]);
    }
}

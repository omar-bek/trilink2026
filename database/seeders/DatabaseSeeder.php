<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            // UNSPSC top-level segments seeded BEFORE the business seeder
            // so demo categories can optionally reference the taxonomy.
            UnspscSegmentsSeeder::class,
            // Realistic UAE B2B data where every company acts as both
            // BUYER and SUPPLIER — each publishes RFQs AND bids on other
            // companies' RFQs, so the dual-role flow is exercised end
            // to end: bid → negotiation → contract → escrow → payment
            // → tax invoice → shipment → feedback.
            RealisticDualRoleSeeder::class,
        ]);
    }
}

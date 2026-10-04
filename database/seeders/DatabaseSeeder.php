<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed only deterministic reference/configuration data.
     *
     * Demo accounts and synthetic operational records are intentionally kept
     * out of the normal seeder so production does not acquire fake data and
     * so this seeder remains safe to run repeatedly.
     */
    public function run(): void
    {
        $this->call([
            FarmerOrganizationSeeder::class,
            KnowledgeBaseSeeder::class,
            AdvisoryRuleSeeder::class,
        ]);
    }
}

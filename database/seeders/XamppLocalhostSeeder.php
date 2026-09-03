<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class XamppLocalhostSeeder extends Seeder
{
    /**
     * Seed the XAMPP localhost MySQL database with the system's baseline data.
     */
    public function run(): void
    {
        $connection = config('database.default');
        $mysql = config('database.connections.mysql');

        if ($connection !== 'mysql') {
            throw new RuntimeException('XamppLocalhostSeeder expects DB_CONNECTION=mysql.');
        }

        if (! in_array($mysql['host'] ?? null, ['127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('XamppLocalhostSeeder only seeds a localhost XAMPP database.');
        }

        $expectedDatabase = env('DB_DATABASE', 'iclimate_db');

        if (($mysql['database'] ?? null) !== $expectedDatabase) {
            throw new RuntimeException("XamppLocalhostSeeder expects DB_DATABASE={$expectedDatabase}.");
        }

        $this->call(DatabaseSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local and test data only. Staging gets a sanitised copy of production instead, and production
     * is never seeded.
     */
    public function run(): void
    {
        $this->call(LocalSeeder::class);
    }
}

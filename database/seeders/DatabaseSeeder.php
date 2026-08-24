<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionCommuneSeeder::class,
            TaxonomySeeder::class,
            SettingsSeeder::class,
            UserSeeder::class,
            ContentSeeder::class,
            ActivitySeeder::class,
        ]);
    }
}

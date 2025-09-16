<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class,      // Users first (needed for product creation)
            ProductSeeder::class,   // Products second (needed for cart items)
            CustomerSeeder::class,  // Customers last (creates cart items)
        ]);
    }
}
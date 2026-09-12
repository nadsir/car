<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            CategoryAttributeSeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
            ProductImageSeeder::class,
        ]);
    }
}

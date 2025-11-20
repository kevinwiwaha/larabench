<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(54321);

        $products = [];
        $batchSize = 500;
        $target = 2000;

        for ($i = 0; $i < $target; $i++) {
            $products[] = [
                'sku'         => 'SKU-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'name'        => $faker->sentence(3),
                'description' => $faker->paragraph(),
                'price'       => $faker->randomFloat(2, 5, 500),
                'stock'       => $faker->numberBetween(0, 500),
                'created_at'  => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at'  => now(),
            ];

            if (count($products) >= $batchSize) {
                Product::insert($products);
                $products = [];
            }
        }

        if (!empty($products)) {
            Product::insert($products);
        }

        $this->command->info('Created 2,000 products');
    }
}


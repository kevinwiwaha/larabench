<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(99999);

        // Fetch all IDs efficiently
        $userIds = User::pluck('id')->all();
        $productIds = Product::pluck('id')->all();

        if (empty($userIds) || empty($productIds)) {
            $this->command->warn('Users or Products not seeded. Skipping orders.');
            return;
        }

        // Cache product prices to avoid N queries
        $productPrices = Product::pluck('price', 'id')->all();

        $orders = [];
        $batchSize = 500;
        $target = 1000;

        for ($i = 0; $i < $target; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $productId = $productIds[array_rand($productIds)];
            $quantity = $faker->numberBetween(1, 5);
            $unitPrice = $productPrices[$productId];
            $totalPrice = $unitPrice * $quantity;

            $orders[] = [
                'user_id'     => $userId,
                'product_id'  => $productId,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'total_price' => $totalPrice,
                'status'      => 'paid',
                'created_at'  => $faker->dateTimeBetween('-3 months', 'now'),
                'updated_at'  => now(),
            ];

            if (count($orders) >= $batchSize) {
                Order::insert($orders);
                $orders = [];
            }
        }

        if (!empty($orders)) {
            Order::insert($orders);
        }

        $this->command->info('Created 1,000 orders');
    }
}


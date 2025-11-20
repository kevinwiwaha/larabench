<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // IMPORTANT: Set seed for deterministic data
        $faker = Faker::create();
        $faker->seed(12345);

        // Pre-hash password once to avoid repeated hashing overhead
        $hashedPassword = Hash::make('password');

        // Known user for testing
        User::updateOrCreate(
            ['email' => 'benchmark@example.com'],
            [
                'name' => 'Benchmark User',
                'password' => $hashedPassword,
            ]
        );

        // Bulk insert for performance
        $users = [];
        $batchSize = 500;
        $target = 1000;

        for ($i = 0; $i < $target; $i++) {
            $users[] = [
                'name'       => $faker->name(),
                'email'      => $faker->unique()->safeEmail(),
                'password'   => $hashedPassword,
                'created_at' => $faker->dateTimeBetween('-6 months', 'now'),
                'updated_at' => now(),
            ];

            // Insert in batches to avoid memory issues
            if (count($users) >= $batchSize) {
                User::insert($users);
                $users = [];
            }
        }

        if (!empty($users)) {
            User::insert($users);
        }

        $this->command->info('Created 1,000 users');
    }
}


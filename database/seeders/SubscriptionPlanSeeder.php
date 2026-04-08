<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'id'                  => '10c0f4ae-ee28-4136-b9f4-617970f31408',
                'name'                => 'Basic',
                'slug'                => 'basic',
                'max_students'        => 100,
                'max_teachers'        => 10,
                'max_exams_per_term'  => 5,
                'price_monthly'       => 5000,
                'price_yearly'        => 50000,
                'features'            => json_encode([
                    'cbt_exams'        => true,
                    'result_analytics' => false,
                    'custom_branding'  => false,
                    'api_access'       => false,
                    'priority_support' => false,
                ]),
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'id'                  => '7b6ef9e8-bf8d-4324-b32b-4b8cc2e6618d',
                'name'                => 'Standard',
                'slug'                => 'standard',
                'max_students'        => 500,
                'max_teachers'        => 30,
                'max_exams_per_term'  => 20,
                'price_monthly'       => 15000,
                'price_yearly'        => 150000,
                'features'            => json_encode([
                    'cbt_exams'        => true,
                    'result_analytics' => true,
                    'custom_branding'  => false,
                    'api_access'       => false,
                    'priority_support' => false,
                ]),
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'id'                  => 'dcdde34f-27c0-4a88-91e0-a75cf298641b', 
                'name'                => 'Premium',
                'slug'                => 'premium',
                'max_students'        => 2000,
                'max_teachers'        => 100,
                'max_exams_per_term'  => 999,
                'price_monthly'       => 35000,
                'price_yearly'        => 350000,
                'features'            => json_encode([
                    'cbt_exams'        => true,
                    'result_analytics' => true,
                    'custom_branding'  => true,
                    'api_access'       => true,
                    'priority_support' => true,
                ]),
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(
                ['id' => $plan['id']], 
                $plan
            );
        }

        $this->command?->info('Subscription plans seeded successfully.');
    }
}
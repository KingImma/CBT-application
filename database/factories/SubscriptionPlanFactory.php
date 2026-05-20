<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /**
     * Generates a basic subscription plan.
     * Features are kept minimal — tests that need specific feature flags
     * should override via state or direct array merge.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Free', 'Standard', 'Premium']),
            'slug' => fake()->unique()->slug(2),
            'max_students' => 200,
            'max_teachers' => 20,
            'max_exams_per_term' => 10,
            'features' => json_encode([
                'theory_questions' => false,
                'image_upload' => false,
                'analytics' => false,
                'csv_export' => false,
            ]),
            'price_monthly' => 0,
            'price_yearly' => 0,
            'is_active' => true,
        ];
    }
}

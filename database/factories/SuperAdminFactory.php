<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generates fake SuperAdmin records for testing.
 * Password defaults to 'password' to keep test setup simple and consistent.
 * @extends Factory<Model>
 */
class SuperAdminFactory extends Factory
{
    protected $model = SuperAdmin::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'password'  => 'password', // cast to hashed automatically via model casts
            'is_active' => true,
        ];
    }

    /**
     * State for an inactive super admin.
     * Useful for testing that inactive admins cannot log in.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
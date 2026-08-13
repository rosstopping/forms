<?php

namespace Database\Factories;

use App\Models\Optimisation;
use App\Models\OptimisationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptimisationVersion>
 */
class OptimisationVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'optimisation_id' => Optimisation::factory(),
            'version' => 1,
            'original_value' => fake()->sentence(),
            'new_value' => fake()->sentence(),
        ];
    }
}

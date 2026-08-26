<?php

namespace Database\Factories;

use App\Models\ProspectingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectingLocation>
 */
class ProspectingLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'slug' => fake()->unique()->slug(),
            'enabled' => true,
            'priority' => 50,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentRequest>
 */
class ContentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'created_by' => User::factory(),
            'instructions' => fake()->paragraph(),
        ];
    }
}

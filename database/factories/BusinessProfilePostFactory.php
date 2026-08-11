<?php

namespace Database\Factories;

use App\Models\BusinessProfileConnection;
use App\Models\BusinessProfilePost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfilePost>
 */
class BusinessProfilePostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_profile_connection_id' => BusinessProfileConnection::factory(),
            'status' => BusinessProfilePost::STATUS_PENDING_APPROVAL,
            'topic' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfileRecommendation>
 */
class BusinessProfileRecommendationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_profile_audit_id' => BusinessProfileAudit::factory(),
            'key' => fake()->unique()->slug(2),
            'severity' => 'warning',
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'status' => BusinessProfileRecommendation::STATUS_PENDING,
        ];
    }
}

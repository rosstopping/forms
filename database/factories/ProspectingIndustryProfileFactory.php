<?php

namespace Database\Factories;

use App\Models\ProspectingIndustryProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectingIndustryProfile>
 */
class ProspectingIndustryProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'enabled' => true,
            'priority' => 50,
            'estimated_customer_value' => 5000,
            'customer_value_band' => 'high',
            'service_keywords' => ['commercial service'],
            'search_keywords' => ['commercial service'],
            'minimum_position' => 8,
            'maximum_position' => 50,
            'maximum_site_size' => 30,
            'automatic_import_score' => 65,
            'notes' => fake()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\User;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentGeneration>
 */
class ContentGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_plan_id' => ContentPlan::factory(),
            'website_repository_id' => WebsiteRepository::factory(),
            'requested_by' => User::factory(),
            'scheduled_for' => today(),
            'status' => ContentGeneration::STATUS_PENDING,
        ];
    }
}

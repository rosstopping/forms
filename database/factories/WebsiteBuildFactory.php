<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebsiteBuild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteBuild>
 */
class WebsiteBuildFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'status' => WebsiteBuild::STATUS_QUEUED,
            'details' => [
                'name' => fake()->company(),
                'sector' => 'Consulting',
                'description' => fake()->sentence(),
                'pages' => ['Home', 'About', 'Contact'],
                'repository_name' => fake()->slug(2),
                'github_installation_id' => fake()->numberBetween(1, 1000),
                'user_id' => null,
            ],
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}

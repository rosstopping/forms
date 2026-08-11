<?php

namespace Database\Factories;

use App\Models\GithubInstallation;
use App\Models\Website;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteRepository>
 */
class WebsiteRepositoryFactory extends Factory
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
            'github_installation_id' => GithubInstallation::factory(),
            'repository_id' => fake()->unique()->numberBetween(1000, 999999),
            'full_name' => fake()->userName().'/'.fake()->slug(2),
            'default_branch' => 'main',
            'private' => true,
            'permissions' => ['admin' => true, 'push' => true, 'pull' => true],
            'project_path' => null,
        ];
    }
}

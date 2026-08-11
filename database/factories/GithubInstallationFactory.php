<?php

namespace Database\Factories;

use App\Models\GithubInstallation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubInstallation>
 */
class GithubInstallationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installed_by' => User::factory(),
            'installation_id' => fake()->unique()->numberBetween(1000, 999999),
            'account_id' => fake()->unique()->numberBetween(1000, 999999),
            'account_login' => fake()->userName(),
            'account_type' => 'Organization',
            'repository_selection' => 'selected',
            'permissions' => ['contents' => 'write', 'pull_requests' => 'write'],
            'status' => GithubInstallation::STATUS_ACTIVE,
        ];
    }
}

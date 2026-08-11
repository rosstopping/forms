<?php

namespace Database\Factories;

use App\Models\GithubUserAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubUserAuthorization>
 */
class GithubUserAuthorizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'github_user_id' => fake()->unique()->numberBetween(1000, 999999),
            'github_login' => fake()->userName(),
            'access_token' => 'ghu_test_access_token',
            'access_token_expires_at' => now()->addHours(8),
            'refresh_token' => 'ghr_test_refresh_token',
            'refresh_token_expires_at' => now()->addMonths(6),
        ];
    }
}

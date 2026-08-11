<?php

namespace Database\Factories;

use App\Models\BusinessProfileConnection;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfileConnection>
 */
class BusinessProfileConnectionFactory extends Factory
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
            'connected_by' => User::factory(),
            'account_name' => 'accounts/123',
            'location_name' => 'locations/456',
            'location_title' => fake()->company(),
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->addHour(),
            'weekly_audits_enabled' => true,
            'weekly_posts_enabled' => false,
            'post_weekday' => 1,
            'post_hour' => 9,
            'timezone' => 'Europe/London',
        ];
    }
}

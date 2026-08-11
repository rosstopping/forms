<?php

namespace Database\Factories;

use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchConsoleConnection>
 */
class SearchConsoleConnectionFactory extends Factory
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
            'property_url' => 'sc-domain:'.$this->faker->domainName(),
            'permission_level' => 'siteOwner',
            'access_token' => $this->faker->sha256(),
            'access_token_expires_at' => now()->addHour(),
            'refresh_token' => $this->faker->sha256(),
        ];
    }
}

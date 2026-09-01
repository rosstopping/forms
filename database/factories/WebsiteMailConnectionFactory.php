<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteMailConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteMailConnection>
 */
class WebsiteMailConnectionFactory extends Factory
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
            'mode' => WebsiteMailConnection::MODE_LEGACY,
            'status' => 'active',
            'connected_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\PixelPageSighting;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PixelPageSighting>
 */
class PixelPageSightingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'website_id' => Website::factory(),
            'url_hash' => hash('sha256', $url),
            'url' => $url,
            'hostname' => parse_url($url, PHP_URL_HOST),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}

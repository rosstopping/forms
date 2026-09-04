<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WordpressConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WordpressConnection>
 */
class WordpressConnectionFactory extends Factory
{
    public function definition(): array
    {
        $credential = 'swp_'.Str::random(64);

        return [
            'website_id' => Website::factory(),
            'public_id' => 'wpc_'.Str::lower(Str::random(28)),
            'credential_hash' => hash('sha256', $credential),
            'wordpress_url' => 'https://'.fake()->unique()->domainName(),
            'plugin_version' => '0.2.0',
            'connected_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'pairing_code_hash' => hash_hmac('sha256', 'ABCDEFGH1234', (string) config('app.key')),
            'pairing_code_expires_at' => now()->addMinutes(10),
            'credential_hash' => null,
            'wordpress_url' => null,
            'plugin_version' => null,
            'connected_at' => null,
            'last_seen_at' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\WebsiteAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteAudit>
 */
class WebsiteAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_url' => 'https://'.fake()->domainName(),
            'domain' => fake()->domainName(),
            'status' => WebsiteAudit::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ];
    }
}

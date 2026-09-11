<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkAudit>
 */
class BacklinkAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'domain' => 'example.com', 'status' => 'pending', 'stages' => [], 'errors' => [], 'limits' => config('services.dataforseo.backlink_audits')];
    }
}

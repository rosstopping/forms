<?php

namespace Database\Factories;

use App\Models\CompetitorAudit;
use App\Models\WebsiteCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompetitorAudit> */
class CompetitorAuditFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['website_competitor_id' => WebsiteCompetitor::factory(), 'website_id' => fn (array $attributes) => WebsiteCompetitor::findOrFail($attributes['website_competitor_id'])->website_id, 'domain' => 'example.com', 'competitor_domain' => fn (array $attributes) => WebsiteCompetitor::findOrFail($attributes['website_competitor_id'])->domain, 'location_code' => 2826, 'language_code' => 'en', 'status' => 'pending', 'stages' => [], 'errors' => [], 'limits' => config('services.dataforseo.competitor_audits')];
    }
}

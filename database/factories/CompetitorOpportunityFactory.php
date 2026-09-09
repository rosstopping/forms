<?php

namespace Database\Factories;

use App\Models\CompetitorAudit;
use App\Models\CompetitorOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompetitorOpportunity> */
class CompetitorOpportunityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['competitor_audit_id' => CompetitorAudit::factory(), 'website_id' => fn (array $attributes) => CompetitorAudit::findOrFail($attributes['competitor_audit_id'])->website_id, 'fingerprint' => fake()->sha256(), 'title' => 'Improve our garden office guide', 'priority_score' => 350, 'brief' => ['title' => 'Improve our garden office guide', 'relevance' => 3, 'existing_page_url' => '', 'source_urls' => ['https://competitor.example/guide/'], 'improvements' => ['Explain the installation process.'], 'outline' => ['Planning', 'Installation'], 'data_source' => 'dataforseo_estimate']];
    }
}

<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\BacklinkAuditCompetitor;
use App\Models\WebsiteCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkAuditCompetitor>
 */
class BacklinkAuditCompetitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['backlink_audit_id' => BacklinkAudit::factory(), 'website_competitor_id' => WebsiteCompetitor::factory(), 'domain' => fake()->domainName()];
    }
}

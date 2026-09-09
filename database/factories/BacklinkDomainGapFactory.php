<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\BacklinkDomainGap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkDomainGap>
 */
class BacklinkDomainGapFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['backlink_audit_id' => BacklinkAudit::factory(), 'domain' => fake()->domainName(), 'domain_rank' => 55, 'competitor_count' => 2, 'competitor_evidence' => [], 'priority_score' => 220];
    }
}

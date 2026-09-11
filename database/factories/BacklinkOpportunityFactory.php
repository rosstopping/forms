<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\BacklinkOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkOpportunity>
 */
class BacklinkOpportunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['backlink_audit_id' => BacklinkAudit::factory(), 'website_id' => fn (array $attributes) => BacklinkAudit::findOrFail($attributes['backlink_audit_id'])->website_id, 'fingerprint' => fake()->sha256(), 'type' => 'content', 'title' => 'Create a stronger original guide', 'priority_score' => 300, 'evidence' => ['observations' => ['Competitor pages attract links.'], 'hypotheses' => [], 'improvements' => ['Add original examples.'], 'outline' => ['Introduction', 'Examples']]];
    }
}

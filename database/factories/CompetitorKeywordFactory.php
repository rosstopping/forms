<?php

namespace Database\Factories;

use App\Models\CompetitorAudit;
use App\Models\CompetitorKeyword;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompetitorKeyword> */
class CompetitorKeywordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['competitor_audit_id' => CompetitorAudit::factory(), 'keyword' => fake()->unique()->words(3, true), 'fingerprint' => fake()->sha256(), 'position' => 3, 'ranking_url' => 'https://competitor.example/guide/', 'comparison' => 'missing', 'search_volume' => 100, 'search_intent' => 'commercial'];
    }
}

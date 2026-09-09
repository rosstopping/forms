<?php

namespace Database\Factories;

use App\Models\CompetitorAudit;
use App\Models\CompetitorPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompetitorPage> */
class CompetitorPageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['competitor_audit_id' => CompetitorAudit::factory(), 'url' => 'https://competitor.example/guide/', 'url_hash' => hash('sha256', 'https://competitor.example/guide/'), 'status' => 'pending'];
    }
}

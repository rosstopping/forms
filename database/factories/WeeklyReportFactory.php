<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WeeklyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeeklyReport> */
class WeeklyReportFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'period_start' => today()->subDays(7), 'period_end' => today()->subDay(), 'snapshot' => ['sections' => [], 'metric_cards' => [], 'completed_work' => []], 'overview' => 'Your weekly overview.', 'narrative_source' => 'deterministic', 'generated_at' => now()];
    }
}

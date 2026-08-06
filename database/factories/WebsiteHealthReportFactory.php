<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteHealthReport>
 */
class WebsiteHealthReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'status' => WebsiteHealthReport::STATUS_COMPLETED,
            'overall_status' => 'healthy',
            'passed_checks' => 5,
            'warning_checks' => 0,
            'failed_checks' => 0,
            'categories' => [],
            'checks' => [],
            'metrics' => [],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}

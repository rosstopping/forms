<?php

namespace Database\Factories;

use App\Models\RemediationRun;
use App\Models\User;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemediationRun>
 */
class RemediationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_health_report_id' => WebsiteHealthReport::factory(),
            'website_repository_id' => WebsiteRepository::factory(),
            'requested_by' => User::factory(),
            'status' => RemediationRun::STATUS_AWAITING_RUNNER,
            'findings' => [],
            'copilot_task_id' => null,
        ];
    }
}

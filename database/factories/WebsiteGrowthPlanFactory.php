<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGrowthPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteGrowthPlan> */
class WebsiteGrowthPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'created_by' => User::factory()->state(['role' => User::ROLE_ADMIN]),
            'brief' => ['services' => 'Heating installations', 'locations' => 'Sheffield', 'audience' => 'Homeowners', 'goals' => 'More enquiries'],
            'report' => ['website_name' => 'Example website', 'domain' => 'example.com', 'generated_at' => now()->toIso8601String(), 'summary' => 'A plan to grow qualified enquiries.', 'keywords' => [], 'roadmap' => [], 'health' => null, 'competitors' => [], 'success_measure' => 'Qualified enquiries', 'dependencies' => 'Access and approval'],
        ];
    }
}

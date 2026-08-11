<?php

namespace Database\Factories;

use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfileConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfileAudit>
 */
class BusinessProfileAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_profile_connection_id' => BusinessProfileConnection::factory(),
            'status' => BusinessProfileAudit::STATUS_COMPLETED,
            'overall_status' => 'healthy',
            'snapshot' => ['title' => fake()->company()],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }
}

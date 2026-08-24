<?php

namespace Database\Factories;

use App\Enums\ProspectEngagementEventType;
use App\Models\Prospect;
use App\Models\ProspectEngagementEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectEngagementEvent>
 */
class ProspectEngagementEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::factory(),
            'event_type' => ProspectEngagementEventType::EmailOpened,
            'source' => 'tracking',
            'fingerprint' => fake()->uuid(),
            'score_delta' => 1,
            'occurred_at' => now(),
        ];
    }
}

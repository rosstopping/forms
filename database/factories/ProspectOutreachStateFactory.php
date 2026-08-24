<?php

namespace Database\Factories;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectOutreachState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectOutreachState>
 */
class ProspectOutreachStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_id' => fn (): int => Prospect::withoutEvents(fn (): Prospect => Prospect::factory()->create())->id,
            'lifecycle_state' => ProspectLifecycleState::New,
            'engagement_score' => 0,
            'automation_status' => ProspectAutomationStatus::Active,
            'sequence_step' => ProspectSequenceStep::AwaitingInitialEmail,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\OnboardingLifecycleStep;
use App\Models\OnboardingLifecycleMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingLifecycleMessage>
 */
class OnboardingLifecycleMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'step' => OnboardingLifecycleStep::Welcome,
            'audience' => 'customer',
            'scheduled_for' => now(),
        ];
    }
}

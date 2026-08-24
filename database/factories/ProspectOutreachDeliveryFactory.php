<?php

namespace Database\Factories;

use App\Enums\ProspectOutreachMessageType;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectOutreachDelivery>
 */
class ProspectOutreachDeliveryFactory extends Factory
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
            'recipient_email' => fake()->safeEmail(),
            'message_type' => ProspectOutreachMessageType::Initial,
            'status' => 'sent',
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'sent_at' => now(),
        ];
    }
}

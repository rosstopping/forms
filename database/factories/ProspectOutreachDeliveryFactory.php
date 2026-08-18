<?php

namespace Database\Factories;

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
            'sent_at' => now(),
        ];
    }
}

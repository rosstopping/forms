<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\ProspectActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectActivity>
 */
class ProspectActivityFactory extends Factory
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
            'type' => 'created',
            'description' => fake()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ProspectDiscovery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectDiscovery>
 */
class ProspectDiscoveryFactory extends Factory
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
            'area' => fake()->city(),
            'business_type' => 'tradespeople',
        ];
    }
}

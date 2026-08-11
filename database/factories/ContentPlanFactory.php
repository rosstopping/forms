<?php

namespace Database\Factories;

use App\Models\ContentPlan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPlan>
 */
class ContentPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'created_by' => User::factory(),
            'enabled' => true,
            'weekday' => 1,
            'hour' => 8,
            'timezone' => 'Europe/London',
            'audience' => $this->faker->sentence(),
            'guidance' => $this->faker->sentence(),
        ];
    }
}

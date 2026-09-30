<?php

namespace Database\Factories;

use App\Models\MarketingConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingConversion>
 */
class MarketingConversionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => fake()->uuid(),
            'deduplication_key' => hash('sha256', fake()->uuid()),
            'name' => 'book_call_clicked',
            'attribution' => ['journey_id' => fake()->uuid()],
        ];
    }
}

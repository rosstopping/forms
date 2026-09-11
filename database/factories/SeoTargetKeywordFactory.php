<?php

namespace Database\Factories;

use App\Models\SeoTargetKeyword;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTargetKeyword>
 */
class SeoTargetKeywordFactory extends Factory
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
            'term' => fake()->unique()->words(3, true),
            'priority' => SeoTargetKeyword::PRIORITY_NORMAL,
            'note' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTargetKeywordRanking>
 */
class SeoTargetKeywordRankingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_target_keyword_id' => SeoTargetKeyword::factory(),
            'website_id' => fn (array $attributes) => SeoTargetKeyword::find($attributes['seo_target_keyword_id'])?->website_id,
            'provider' => 'dataforseo', 'location_code' => 2826, 'language_code' => 'en', 'device' => 'desktop',
            'status' => SeoTargetKeywordRanking::STATUS_RANKED, 'position' => fake()->numberBetween(1, 100),
            'ranking_url' => fake()->url(), 'cached' => false, 'observed_at' => now(),
        ];
    }
}

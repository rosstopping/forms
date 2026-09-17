<?php

namespace Database\Factories;

use App\Models\SeoImpact;
use App\Models\SeoImpactReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoImpactReview> */
class SeoImpactReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['seo_impact_id' => SeoImpact::factory(), 'checkpoint' => 28, 'period_start' => today()->subDays(30), 'period_end' => today()->subDays(3), 'baseline' => [], 'measurement' => [], 'assessment' => ['reason' => 'Not enough search data yet.'], 'outcome' => 'insufficient_data'];
    }
}

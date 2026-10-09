<?php

namespace Database\Factories;

use App\Models\SeoWin;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoWin> */
class SeoWinFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'rule' => 'top_10', 'rule_version' => 1, 'importance' => 'normal', 'confidence' => 'medium',
            'title' => 'Entered the top 10 · garden design', 'evidence' => ['keyword' => 'garden design', 'market' => ['location_code' => 2826, 'language_code' => 'en', 'device' => 'desktop'], 'observations' => []],
            'observed_at' => now()->subDays(7), 'confirmed_at' => now(), 'client_draft' => 'Quick SEO update — garden design has moved into the top 10.'];
    }
}

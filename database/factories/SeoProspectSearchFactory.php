<?php

namespace Database\Factories;

use App\Models\SeoProspectSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoProspectSearch>
 */
class SeoProspectSearchFactory extends Factory
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
            'industry' => 'Roofing',
            'location' => 'Barnsley',
            'service_keywords' => ['roofer', 'roof repairs'],
            'keywords' => ['roofer barnsley', 'roof repairs barnsley'],
        ];
    }
}

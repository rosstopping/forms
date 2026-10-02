<?php

namespace Database\Factories;

use App\Models\GoogleAdsCampaignDraft;
use App\Models\GoogleAdsConnection;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoogleAdsCampaignDraft>
 */
class GoogleAdsCampaignDraftFactory extends Factory
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
            'google_ads_connection_id' => GoogleAdsConnection::factory(),
            'request_key' => (string) Str::uuid(),
            'customer_id' => '1234567890',
            'name' => 'Example search campaign',
            'daily_budget_micros' => 10000000,
            'max_cpc_micros' => 2000000,
            'city_name' => 'Doncaster',
            'country_code' => 'GB',
            'radius_miles' => 20,
            'final_url' => 'https://example.com/',
            'keywords' => ['example services'],
            'headlines' => ['Example Services', 'Talk to Our Team', 'Get in Touch Today'],
            'descriptions' => ['Find out how our team can help.', 'Visit our website to see our services.'],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteHealthReportPage>
 */
class WebsiteHealthReportPageFactory extends Factory
{
    protected $model = WebsiteHealthReportPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'website_health_report_id' => WebsiteHealthReport::factory(),
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'depth' => 0,
            'status_code' => 200,
            'response_time_ms' => fake()->numberBetween(50, 1500),
            'title' => fake()->sentence(4),
            'meta_description' => fake()->sentence(12),
            'h1_count' => 1,
            'canonical_url' => $url,
            'is_indexable' => true,
            'word_count' => fake()->numberBetween(150, 1000),
            'internal_links_count' => fake()->numberBetween(1, 20),
            'images_count' => fake()->numberBetween(0, 10),
            'missing_alt_count' => 0,
            'checks' => [],
        ];
    }
}

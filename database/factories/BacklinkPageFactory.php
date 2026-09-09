<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\BacklinkPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkPage>
 */
class BacklinkPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://example.com/'.fake()->slug();

        return ['backlink_audit_id' => BacklinkAudit::factory(), 'domain' => 'example.com', 'kind' => 'own', 'url' => $url, 'url_hash' => hash('sha256', $url), 'backlinks' => 12, 'referring_domains' => 5];
    }
}

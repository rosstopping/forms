<?php

namespace Database\Factories;

use App\Models\BacklinkAudit;
use App\Models\BacklinkLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklinkLink>
 */
class BacklinkLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $source = fake()->url();
        $target = 'https://example.com/'.fake()->slug();

        return ['backlink_audit_id' => BacklinkAudit::factory(), 'fingerprint' => hash('sha256', $source.'|'.$target), 'state' => 'live', 'source_domain' => parse_url($source, PHP_URL_HOST), 'source_url' => $source, 'target_url' => $target, 'anchor' => fake()->words(3, true), 'dofollow' => true, 'source_domain_rank' => 45, 'links_count' => 1];
    }
}

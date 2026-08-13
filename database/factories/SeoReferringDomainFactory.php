<?php

namespace Database\Factories;

use App\Models\SeoReferringDomain;
use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoReferringDomain>
 */
class SeoReferringDomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_snapshot_id' => SeoSnapshot::factory(),
            'website_id' => fn (array $attributes): int => SeoSnapshot::query()->findOrFail($attributes['seo_snapshot_id'])->website_id,
            'domain' => fake()->unique()->domainName(),
            'domain_rank' => fake()->numberBetween(0, 100),
            'backlinks_count' => fake()->numberBetween(1, 1000),
            'first_seen' => now()->subMonths(3),
            'last_seen' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Website;
use App\Models\WordpressStaticRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WordpressStaticRelease>
 */
class WordpressStaticReleaseFactory extends Factory
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
            'created_by' => User::factory(),
            'commit_sha' => fake()->sha1(),
            'source_ref' => 'main',
            'status' => WordpressStaticRelease::STATUS_READY,
            'storage_path' => 'wordpress-releases/'.fake()->uuid().'.zip',
            'checksum' => hash('sha256', fake()->uuid()),
            'size' => fake()->numberBetween(1000, 100000),
            'ready_at' => now(),
        ];
    }
}

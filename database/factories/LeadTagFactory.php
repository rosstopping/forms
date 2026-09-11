<?php

namespace Database\Factories;

use App\Models\LeadTag;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeadTag> */
class LeadTagFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'name' => fake()->unique()->words(2, true)];
    }
}

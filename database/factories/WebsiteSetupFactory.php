<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteSetup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteSetup> */
class WebsiteSetupFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'data' => [], 'saved_steps' => [], 'current_step' => 'business'];
    }
}

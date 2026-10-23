<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteLocalProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteLocalProject> */
class WebsiteLocalProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'host' => 'local', 'path' => '/Users/example/Code/'.fake()->slug(), 'codex_project_id' => fake()->uuid()];
    }
}

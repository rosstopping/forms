<?php

namespace Database\Factories;

use App\Models\AiVisibilityPrompt;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiVisibilityPrompt> */
class AiVisibilityPromptFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'prompt' => 'Who would you recommend for '.fake()->unique()->words(3, true).' in Doncaster?', 'topic' => 'Heating', 'location' => 'Doncaster', 'active' => true, 'priority' => 'normal'];
    }
}

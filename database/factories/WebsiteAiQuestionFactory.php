<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteAiQuestion>
 */
class WebsiteAiQuestionFactory extends Factory
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
            'user_id' => User::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'status' => 'completed',
            'error' => null,
        ];
    }
}

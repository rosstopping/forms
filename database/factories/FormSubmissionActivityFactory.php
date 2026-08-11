<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\FormSubmissionActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmissionActivity>
 */
class FormSubmissionActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_submission_id' => FormSubmission::factory(),
            'user_id' => null,
            'type' => 'note_added',
            'description' => fake()->sentence(),
            'metadata' => null,
        ];
    }
}

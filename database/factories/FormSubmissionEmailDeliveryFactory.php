<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\FormSubmissionEmailDelivery;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmissionEmailDelivery>
 */
class FormSubmissionEmailDeliveryFactory extends Factory
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
            'website_id' => Website::factory(),
            'type' => 'autoresponder',
            'mode' => 'legacy',
            'status' => 'queued',
            'recipient' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
        ];
    }
}

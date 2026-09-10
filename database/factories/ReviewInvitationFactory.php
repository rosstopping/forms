<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\ReviewInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewInvitation> */
class ReviewInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_submission_id' => FormSubmission::factory(),
            'website_id' => fn (array $attributes) => FormSubmission::findOrFail($attributes['form_submission_id'])->website_id,
            'requested_by' => User::factory(),
            'recipient' => 'customer@example.com', 'subject' => 'Share your experience',
            'from_email' => 'mail@digizu.co.uk', 'from_name' => 'Example Business',
            'body' => 'Please share an honest review of your experience.', 'review_url' => 'https://example.com/review',
        ];
    }
}

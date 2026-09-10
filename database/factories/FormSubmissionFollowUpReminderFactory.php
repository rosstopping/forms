<?php

namespace Database\Factories;

use App\Models\FormSubmission;
use App\Models\FormSubmissionFollowUpReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FormSubmissionFollowUpReminder> */
class FormSubmissionFollowUpReminderFactory extends Factory
{
    public function definition(): array
    {
        return ['form_submission_id' => FormSubmission::factory(), 'due_at' => now()->addHour(), 'status' => 'pending'];
    }
}

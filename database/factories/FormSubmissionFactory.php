<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'form_id' => Form::factory(),
            'source_url' => fake()->url(),
            'source_domain' => fake()->domainName(),
            'data' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'is_spam' => false,
        ];
    }
}

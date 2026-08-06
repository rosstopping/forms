<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'name' => fake()->words(2, true).' Form',
            'slug' => fake()->unique()->slug(2),
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled_override' => true,
            'webhook_enabled_override' => false,
            'success_redirect_url_override' => null,
            'failure_redirect_url_override' => null,
        ];
    }
}

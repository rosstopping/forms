<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company().' Website',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled' => true,
            'webhook_enabled' => false,
            'success_redirect_url' => null,
            'failure_redirect_url' => null,
            'turnstile_enabled' => false,
        ];
    }
}

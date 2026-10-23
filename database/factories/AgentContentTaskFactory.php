<?php

namespace Database\Factories;

use App\Models\AgentContentTask;
use App\Models\ContentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentContentTask> */
class AgentContentTaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['content_request_id' => ContentRequest::factory(), 'website_id' => fn (array $attributes) => ContentRequest::findOrFail($attributes['content_request_id'])->website_id,
            'user_id' => User::factory(), 'scheduled_for' => today(), 'timezone' => 'Europe/London', 'fingerprint' => fake()->sha256(),
            'project' => ['host' => 'local', 'path' => '/Users/example/Code/site'], 'repository' => 'example/site',
            'evidence' => [], 'rationale' => 'Improve an evidenced customer need after checking existing coverage.'];
    }
}

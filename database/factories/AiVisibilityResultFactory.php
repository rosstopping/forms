<?php

namespace Database\Factories;

use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilityResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiVisibilityResult> */
class AiVisibilityResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ai_visibility_prompt_id' => AiVisibilityPrompt::factory(),
            'website_id' => fn (array $attributes) => AiVisibilityPrompt::findOrFail($attributes['ai_visibility_prompt_id'])->website_id,
            'prompt_snapshot' => fn (array $attributes) => AiVisibilityPrompt::findOrFail($attributes['ai_visibility_prompt_id'])->prompt,
            'prompt_fingerprint' => fn (array $attributes) => AiVisibilityPrompt::findOrFail($attributes['ai_visibility_prompt_id'])->fingerprint,
            'cohort' => fn (array $attributes) => hash('sha256', $attributes['prompt_fingerprint'].'|'.$attributes['provider']),
            'provider' => 'openai', 'model' => 'test-model', 'identity_snapshot' => ['names' => ['Rowglo'], 'domains' => ['rowglo.co.uk']],
            'period_start' => today()->startOfWeek(), 'status' => 'queued', 'attempts' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'checked_at' => now(), 'started_at' => now(), 'attempts' => 1, 'response_text' => 'No suitable recommendation.', 'brand_mentioned' => false, 'website_mentioned' => false, 'website_cited' => false, 'citations' => [], 'competitors' => [], 'analysis' => ['version' => 1]]);
    }
}

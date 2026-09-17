<?php

namespace Database\Factories;

use App\Models\AiVisibilityResult;
use App\Models\JevShadowEvaluation;
use App\Services\JevAiVisibilityEvaluator;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JevShadowEvaluation> */
class JevShadowEvaluationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ai_visibility_result_id' => AiVisibilityResult::factory()->completed(),
            'website_id' => fn (array $attributes) => AiVisibilityResult::findOrFail($attributes['ai_visibility_result_id'])->website_id,
            'input_hash' => fn (array $attributes) => app(JevAiVisibilityEvaluator::class)->inputHash($this->request($attributes)),
            'model' => config('jev.model'),
            'question_version' => JevAiVisibilityEvaluator::QUESTION_VERSION,
            'request_snapshot' => fn (array $attributes) => $this->request($attributes),
            'baseline_snapshot' => fn (array $attributes) => ['brand_mentioned' => AiVisibilityResult::findOrFail($attributes['ai_visibility_result_id'])->brand_mentioned, 'analysis_version' => 1],
            'status' => 'running',
            'started_at' => now(),
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array{model: string, state: array, questions: array}
     */
    private function request(array $attributes): array
    {
        return app(JevAiVisibilityEvaluator::class)->requestFor(AiVisibilityResult::findOrFail($attributes['ai_visibility_result_id']));
    }
}

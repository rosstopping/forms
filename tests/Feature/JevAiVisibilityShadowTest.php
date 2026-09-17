<?php

use App\Models\AiVisibilityResult;
use App\Models\JevShadowEvaluation;
use App\Services\AiVisibilityReport;
use App\Services\JevAiVisibilityEvaluator;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['jev.enabled' => true, 'jev.key' => 'test-secret', 'jev.model' => 'jev-latest']);
    Http::preventStrayRequests();
});

/** @return array<string, mixed> */
function jevShadowResponse(): array
{
    return [
        'model' => 'jev-test-version',
        'answers' => [
            'brand_mentioned' => ['type' => 'noul', 'noul' => 0.98],
            'business_reference' => [
                'type' => 'choice', 'choice' => 'target_business', 'confidence' => 0.84,
                'probabilities' => ['target_business' => 0.94, 'different_business' => 0.01, 'ambiguous_reference' => 0.04, 'no_business_reference' => 0.01],
            ],
        ],
        'usage' => ['input_tokens' => 900, 'output_tokens' => 40],
    ];
}

test('shadow evaluation preserves the exact evidence and baseline without changing customer data', function (): void {
    $result = AiVisibilityResult::factory()->completed()->create([
        'response_text' => 'I cannot recommend Rowglo.',
        'identity_snapshot' => ['names' => ['Rowglo'], 'domains' => ['rowglo.co.uk'], 'secret' => 'not-for-model'],
    ]);
    $before = $result->fresh()->getRawOriginal();
    $metrics = app(AiVisibilityReport::class)->summarize(collect([$result]));
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response(jevShadowResponse())]);

    $this->artisan('jev:evaluate-ai-visibility')->assertSuccessful();

    $evaluation = JevShadowEvaluation::sole();
    expect($evaluation->status)->toBe('completed')
        ->and($evaluation->mention_probability)->toBe(0.98)
        ->and($evaluation->choice_confidence)->toBe(0.84)
        ->and($evaluation->reference_choice)->toBe('target_business')
        ->and($evaluation->baseline_snapshot['brand_mentioned'])->toBeFalse()
        ->and($evaluation->baseline_snapshot['analysis_version'])->toBe(1)
        ->and($evaluation->returned_model)->toBe('jev-test-version')
        ->and($evaluation->usage)->toBe(jevShadowResponse()['usage'])
        ->and($evaluation->answers)->toBe(jevShadowResponse()['answers'])
        ->and($evaluation->cost)->toBeNull()
        ->and($evaluation->human_label)->toBeNull()
        ->and($evaluation->completed_at)->not->toBeNull()
        ->and($evaluation->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and($evaluation->website->id)->toBe($result->website_id)
        ->and($evaluation->result->id)->toBe($result->id)
        ->and($result->fresh()->getRawOriginal())->toBe($before)
        ->and(app(AiVisibilityReport::class)->summarize(collect([$result->fresh()])))->toBe($metrics);
    Http::assertSent(function (Request $request) use ($evaluation): bool {
        return $request->url() === JevAiVisibilityEvaluator::ENDPOINT
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request->data() === $evaluation->request_snapshot
            && array_keys($request['state']) === ['response_text', 'identity', 'original_question']
            && array_keys($request['state']['identity']) === ['names', 'domains']
            && count($request['questions']) === 2
            && ! str_contains(json_encode($request->data()), 'not-for-model')
            && str_contains($request['questions']['brand_mentioned']['instructions'], 'Negative statements');
    });
    Http::assertSentCount(1);
});

test('disabled or unconfigured evaluation makes no calls or records', function (array $configuration): void {
    AiVisibilityResult::factory()->completed()->create();
    config($configuration);
    $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
    expect(JevShadowEvaluation::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'disabled' => [['jev.enabled' => false]],
    'missing key' => [['jev.key' => null]],
    'blank key' => [['jev.key' => '   ']],
    'missing model' => [['jev.model' => '']],
]);

test('the client itself enforces the opt in', function (): void {
    $result = AiVisibilityResult::factory()->completed()->create();
    config(['jev.enabled' => false]);
    expect(fn () => app(JevAiVisibilityEvaluator::class)->evaluate($result))->toThrow(LogicException::class);
    Http::assertNothingSent();
});

test('duplicate and interrupted evaluations never replay the request', function (): void {
    $result = AiVisibilityResult::factory()->completed()->create();
    $interrupted = AiVisibilityResult::factory()->completed()->create();
    JevShadowEvaluation::factory()->create(['ai_visibility_result_id' => $interrupted->id]);
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response(jevShadowResponse())]);

    $this->artisan('jev:evaluate-ai-visibility')->assertSuccessful();
    $this->artisan('jev:evaluate-ai-visibility')->assertSuccessful();

    expect(JevShadowEvaluation::count())->toBe(2)
        ->and(JevShadowEvaluation::where('ai_visibility_result_id', $interrupted->id)->sole()->status)->toBe('running');
    Http::assertSentCount(1);
});

test('new input model and question versions have distinct evaluation identities', function (): void {
    $result = AiVisibilityResult::factory()->completed()->create();
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response(jevShadowResponse())]);
    $service = app(JevAiVisibilityEvaluator::class);
    $first = $service->evaluate($result);
    $result->update(['response_text' => 'Rowglo appears now.']);
    $second = $service->evaluate($result);
    config(['jev.model' => 'jev-other-version']);
    $third = $service->evaluate($result);
    $third->update(['question_version' => 'older-rubric']);
    $service->evaluate($result);

    expect(JevShadowEvaluation::count())->toBe(4)
        ->and($first->fresh()->request_snapshot['state']['response_text'])->toBe('No suitable recommendation.')
        ->and($first->input_hash)->not->toBe($second->input_hash)
        ->and($second->input_hash)->toBe($third->input_hash);
    Http::assertSentCount(4);
});

test('completed nonempty valid bounded evidence is required', function (array $attributes): void {
    $result = AiVisibilityResult::factory()->completed()->create($attributes);
    expect(app(JevAiVisibilityEvaluator::class)->evaluate($result))->toBeNull()
        ->and(JevShadowEvaluation::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'failed observation' => [['status' => 'failed']],
    'empty observation' => [['response_text' => '   ']],
    'missing identity' => [['identity_snapshot' => []]],
    'invalid name' => [['identity_snapshot' => ['names' => [123], 'domains' => []]]],
    'oversized evidence' => [['response_text' => str_repeat('a', 40001)]],
]);

test('HTTP failures are recorded safely and never automatically retried', function (int $status): void {
    $result = AiVisibilityResult::factory()->completed()->create();
    $before = $result->fresh()->getRawOriginal();
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response(['error' => 'test-secret echoed by upstream'], $status)]);

    $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
    $this->artisan('jev:evaluate-ai-visibility')->assertSuccessful();

    $evaluation = JevShadowEvaluation::sole();
    expect($evaluation->status)->toBe('failed')
        ->and($evaluation->http_status)->toBe($status)
        ->and($evaluation->error_type)->toBe('http_error')
        ->and($evaluation->answers)->toBeNull()
        ->and(json_encode($evaluation->toArray()))->not->toContain('test-secret')
        ->and($result->fresh()->getRawOriginal())->toBe($before);
    Http::assertSentCount(1);
})->with([302, 401, 429, 500]);

test('network failures are isolated to the shadow record', function (): void {
    AiVisibilityResult::factory()->completed()->create();
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::failedConnection()]);
    $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
    expect(JevShadowEvaluation::sole()->error_type)->toBe('connection_error')
        ->and(JevShadowEvaluation::sole()->status)->toBe('failed');
});

test('malformed typed responses fail closed', function (string $path, mixed $value): void {
    AiVisibilityResult::factory()->completed()->create();
    $response = jevShadowResponse();
    data_set($response, $path, $value);
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response($response)]);
    $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
    expect(JevShadowEvaluation::sole()->error_type)->toBe('invalid_response')
        ->and(JevShadowEvaluation::sole()->mention_probability)->toBeNull();
})->with([
    ['model', ''],
    ['answers.brand_mentioned', 'wrong'],
    ['answers.brand_mentioned.type', 'boolean'],
    ['answers.brand_mentioned.noul', '0.98'],
    ['answers.brand_mentioned.noul', true],
    ['answers.brand_mentioned.noul', 1.2],
    ['answers.business_reference', 'wrong'],
    ['answers.business_reference.choice', 'invented'],
    ['answers.business_reference.choice', 'different_business'],
    ['answers.business_reference.confidence', -0.1],
    ['answers.business_reference.probabilities.target_business', 0.5],
    ['answers.business_reference.probabilities.target_business', '0.94'],
    ['answers.business_reference.probabilities.extra', 0],
    ['usage.input_tokens', -1],
]);

test('non JSON and oversized responses are recorded as invalid', function (string $body): void {
    AiVisibilityResult::factory()->completed()->create();
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response($body)]);
    $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
    expect(JevShadowEvaluation::sole()->error_type)->toBe('invalid_response');
})->with(['not-json', str_repeat('x', 65537)]);

test('the command validates all batch options before requesting evaluation', function (array $options): void {
    AiVisibilityResult::factory()->completed()->create();
    $this->artisan('jev:evaluate-ai-visibility', $options)->assertExitCode(2);
    Http::assertNothingSent();
})->with([
    [['--limit' => 0]], [['--limit' => 501]], [['--limit' => '1.5']],
    [['--after-id' => -1]], [['--website' => 0]],
]);

test('bounded batches and cursor scope keep evaluation operator controlled', function (): void {
    $first = AiVisibilityResult::factory()->completed()->create();
    $second = AiVisibilityResult::factory()->completed()->create();
    $third = AiVisibilityResult::factory()->completed()->create();
    Http::fake([JevAiVisibilityEvaluator::ENDPOINT => Http::response(jevShadowResponse())]);

    $this->artisan('jev:evaluate-ai-visibility', ['--limit' => 1, '--after-id' => $first->id])->assertSuccessful();
    expect(JevShadowEvaluation::sole()->ai_visibility_result_id)->toBe($second->id);
    $this->artisan('jev:evaluate-ai-visibility', ['--website' => $third->website_id])->assertSuccessful();
    expect(JevShadowEvaluation::count())->toBe(2);
    Http::assertSentCount(2);
});

test('byte budget stops before sending a request and does not consume its cursor', function (): void {
    $result = AiVisibilityResult::factory()->completed()->create();
    config(['jev.max_batch_bytes' => 1]);
    $this->artisan('jev:evaluate-ai-visibility')
        ->expectsOutputToContain('Last inspected ID: 0.')
        ->assertSuccessful();
    expect(JevShadowEvaluation::count())->toBe(0);
    Http::assertNothingSent();
});

test('runtime budget prevents starting another provider request', function (): void {
    AiVisibilityResult::factory()->completed()->count(2)->create();
    config(['jev.max_run_seconds' => 1]);
    Http::fake(function (): PromiseInterface {
        usleep(1100000);

        return Http::response(jevShadowResponse());
    });
    $this->artisan('jev:evaluate-ai-visibility')->assertSuccessful();
    expect(JevShadowEvaluation::count())->toBe(1);
    Http::assertSentCount(1);
});

test('overlapping batches are refused without requests', function (): void {
    AiVisibilityResult::factory()->completed()->create();
    $lock = Cache::lock('jev-ai-visibility-shadow', 360);
    $lock->get();
    try {
        $this->artisan('jev:evaluate-ai-visibility')->assertFailed();
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }
});

test('deleting source evidence also deletes its isolated shadow snapshot', function (): void {
    $evaluation = JevShadowEvaluation::factory()->create();
    $evaluation->result->delete();
    expect(JevShadowEvaluation::count())->toBe(0);
});

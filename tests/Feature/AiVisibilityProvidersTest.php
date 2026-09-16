<?php

use App\Contracts\AiVisibilityProvider;
use App\Services\AiVisibilityAnalyzer;
use App\Services\AiVisibilityProviderRegistry;
use App\Services\GeminiVisibilityProvider;
use App\Services\OpenAiVisibilityProvider;
use App\Services\PerplexityVisibilityProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['ai.providers.openai.key' => 'fake-openai', 'ai.providers.gemini.key' => 'fake-gemini', 'ai_visibility.providers.openai.enabled' => true, 'ai_visibility.providers.gemini.enabled' => true, 'ai_visibility.providers.perplexity.enabled' => true, 'ai_visibility.providers.perplexity.key' => 'fake-perplexity']);
});

test('openai adapter calls responses with web search and preserves citations without supplying brand hints', function (): void {
    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'response-openai', 'model' => 'openai-test-model', 'status' => 'completed',
        'output' => [['type' => 'web_search_call', 'status' => 'completed'], ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => 'Rowglo is an option.', 'annotations' => [['type' => 'url_citation', 'url' => 'https://rowglo.co.uk', 'title' => 'Rowglo']]]]]],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 8],
    ])]);
    $provider = app(OpenAiVisibilityProvider::class);
    $response = $provider->check('Who repairs boilers in Doncaster?', 'openai-test-model');
    expect($provider)->toBeInstanceOf(AiVisibilityProvider::class)->and($response['metadata']['provider'])->toBe('openai')->and($response['citations'][0]['url'])->toBe('https://rowglo.co.uk')->and($response['usage']['prompt_tokens'])->toBe(10);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses' && $request['model'] === 'openai-test-model' && $request['tools'][0]['type'] === 'web_search' && $request['store'] === false && ! str_contains($request->body(), 'Rowglo'));
    Http::assertSentCount(1);
});

test('gemini adapter queries google and captures grounding sources using its own provider identity', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'modelVersion' => 'gemini-test-model', 'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Rowglo is an option.']]], 'finishReason' => 'STOP', 'groundingMetadata' => ['groundingChunks' => [['web' => ['uri' => 'https://rowglo.co.uk', 'title' => 'Rowglo']]], 'groundingSupports' => [['groundingChunkIndices' => [0]]]]]],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 8],
    ])]);
    $response = app(GeminiVisibilityProvider::class)->check('Who repairs boilers in Doncaster?', 'gemini-test-model');
    expect($response['metadata']['provider'])->toBe('gemini')->and($response['citations'][0]['url'])->toBe('https://rowglo.co.uk');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com') && str_contains($request->url(), 'gemini-test-model:generateContent') && isset($request['tools'][0]['google_search']) && ! str_contains($request->body(), 'Rowglo'));
    Http::assertSentCount(1);
});

test('perplexity adapter uses its own endpoint credentials response and citations', function (): void {
    Http::fake(['api.perplexity.ai/*' => Http::response(['id' => 'perplexity-response', 'choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Other Heating and Rowglo.']]], 'citations' => ['https://rowglo.co.uk'], 'usage' => ['prompt_tokens' => 12]])]);
    $response = app(PerplexityVisibilityProvider::class)->check('Who repairs boilers?', 'sonar');
    expect($response['metadata']['provider'])->toBe('perplexity')->and($response['citations'])->toBe([['url' => 'https://rowglo.co.uk']]);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.perplexity.ai/chat/completions' && $request->hasHeader('Authorization', 'Bearer fake-perplexity') && $request['model'] === 'sonar' && $request['messages'][1]['content'] === 'Who repairs boilers?');
    Http::assertSentCount(1);
});

test('unconfigured providers stay disabled and never fall back to another platform', function (): void {
    config(['ai.providers.gemini.key' => null, 'ai_visibility.providers.perplexity.key' => null]);
    $registry = app(AiVisibilityProviderRegistry::class);
    expect($registry->availability())->toBe(['openai' => true, 'gemini' => false, 'perplexity' => false]);
    expect(fn () => $registry->get('gemini')->check('Who repairs boilers?', 'test'))->toThrow(RuntimeException::class);
    expect(fn () => $registry->get('perplexity')->check('Who repairs boilers?', 'sonar'))->toThrow(RuntimeException::class);
    expect(fn () => $registry->get('chatgpt'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

test('incomplete provider responses are rejected instead of becoming invisible results', function (string $key): void {
    Http::fake([
        'api.openai.com/*' => Http::response(['status' => 'incomplete', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Partial answer.']]]]]),
        'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Partial answer.']]], 'finishReason' => 'MAX_TOKENS']]]),
        'api.perplexity.ai/*' => Http::response(['choices' => [['finish_reason' => 'length', 'message' => ['content' => 'Partial answer.']]]]),
    ]);
    expect(fn () => app(AiVisibilityProviderRegistry::class)->get($key)->check('Who repairs boilers?', 'test-model'))->toThrow(RuntimeException::class);
})->with(['openai', 'gemini', 'perplexity']);

test('redirect citations remain auditable without inventing an original domain', function (): void {
    $result = app(AiVisibilityAnalyzer::class)->analyze('Rowglo is recommended.', [['url' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/example', 'title' => 'rowglo.co.uk']], ['names' => ['Rowglo'], 'domains' => ['rowglo.co.uk']]);
    expect($result['website_cited'])->toBeFalse()->and($result['brand_mentioned'])->toBeTrue()->and($result['citations'][0]['domain'])->toBe('vertexaisearch.cloud.google.com');
    Http::assertNothingSent();
});

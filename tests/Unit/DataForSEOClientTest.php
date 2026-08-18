<?php

use App\Services\DataForSEO\DataForSEOClient;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('services.dataforseo', [
        'login' => 'api-login',
        'password' => 'api-password',
        'api_url' => 'https://api.dataforseo.test/v3',
        'connect_timeout' => 1,
        'timeout' => 2,
        'ranked_keywords_limit' => 500,
        'referring_domains_limit' => 250,
        'competitors_limit' => 25,
        'refresh_days' => 7,
    ]);
    Http::preventStrayRequests();
});

test('it authenticates and returns a validated provider response', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response(successfulDataForSEOResponse())]);

    $response = app(DataForSEOClient::class)->post('dataforseo_labs/google/ranked_keywords/live', [
        'target' => 'example.com',
    ]);

    expect($response->cost)->toBe(0.0103)
        ->and($response->resultCount)->toBe(1)
        ->and($response->taskId)->toBe('task-id')
        ->and($response->results)->toHaveCount(1);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.dataforseo.test/v3/dataforseo_labs/google/ranked_keywords/live'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-login:api-password'))
            && $request->data() === [['target' => 'example.com']];
    });
});

test('it authenticates get requests and returns a validated provider response', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response(successfulDataForSEOResponse())]);

    $response = app(DataForSEOClient::class)->get('serp/google/locations/gb');

    expect($response->results)->toHaveCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.dataforseo.test/v3/serp/google/locations/gb'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('api-login:api-password')));
});

test('it fails before sending a request when credentials are missing', function (): void {
    config()->set('services.dataforseo.login');

    expect(fn () => app(DataForSEOClient::class)->post('backlinks/summary/live', ['target' => 'example.com']))
        ->toThrow(DataForSEOException::class, 'not configured');

    Http::assertNothingSent();
});

test('it rejects unsuccessful provider task responses and logs safe context', function (): void {
    Log::spy();
    Http::fake(['api.dataforseo.test/*' => Http::response([
        'status_code' => 20000,
        'tasks' => [[
            'status_code' => 40501,
            'status_message' => 'Internal provider detail',
            'result' => null,
        ]],
    ])]);

    expect(fn () => app(DataForSEOClient::class)->post('backlinks/summary/live', ['target' => 'example.com']))
        ->toThrow(DataForSEOException::class, 'DataForSEO rejected the request (40501: Internal provider detail).');

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'DataForSEO request failed.'
            && $context['endpoint'] === 'backlinks/summary/live'
            && $context['provider_status_code'] === 40501
            && $context['provider_status_message'] === 'Internal provider detail'
            && ! array_key_exists('password', $context);
    });
});

test('it retries rate limits and returns a safe failure', function (): void {
    Log::spy();
    Http::fake(['api.dataforseo.test/*' => Http::sequence()
        ->push(['status_message' => 'rate limited'], 429)
        ->push(['status_message' => 'rate limited'], 429)
        ->push(['status_message' => 'rate limited'], 429)]);

    expect(fn () => app(DataForSEOClient::class)->post('backlinks/summary/live', ['target' => 'example.com']))
        ->toThrow(DataForSEOException::class, 'rate limit');

    Http::assertSentCount(3);
});

/** @return array<string, mixed> */
function successfulDataForSEOResponse(): array
{
    return [
        'status_code' => 20000,
        'cost' => 0.0103,
        'tasks' => [[
            'id' => 'task-id',
            'status_code' => 20000,
            'cost' => 0.0103,
            'result_count' => 1,
            'result' => [['target' => 'example.com', 'items' => []]],
        ]],
    ];
}

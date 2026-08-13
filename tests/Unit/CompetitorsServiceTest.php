<?php

use App\Services\DataForSEO\CompetitorsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('services.dataforseo', [
        'login' => 'api-login',
        'password' => 'api-password',
        'api_url' => 'https://api.dataforseo.test/v3',
        'connect_timeout' => 1,
        'timeout' => 2,
        'competitors_limit' => 25,
    ]);
    Http::preventStrayRequests();
});

test('it maps and deduplicates organic competitors while excluding the target domain', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response([
        'status_code' => 20000,
        'tasks' => [[
            'id' => 'competitors-task',
            'status_code' => 20000,
            'cost' => 0.0103,
            'result_count' => 1,
            'result' => [['items' => [
                ['domain' => 'example.com', 'intersections' => 139, 'full_domain_metrics' => ['organic' => ['count' => 139, 'etv' => 720.25]]],
                ['domain' => 'Competitor-One.Example', 'intersections' => 63, 'full_domain_metrics' => ['organic' => ['count' => 842, 'etv' => 4200.75]]],
                ['domain' => 'competitor-one.example', 'intersections' => 61, 'full_domain_metrics' => ['organic' => ['count' => 800, 'etv' => 4000]]],
                ['domain' => '', 'intersections' => 4],
            ]]],
        ]],
    ])]);

    $response = app(CompetitorsService::class)->forDomain('Example.COM', 2826, 'en');

    expect($response->competitors)->toHaveCount(1)
        ->and($response->competitors[0]->domain)->toBe('competitor-one.example')
        ->and($response->competitors[0]->commonKeywords)->toBe(63)
        ->and($response->competitors[0]->organicKeywords)->toBe(842)
        ->and($response->competitors[0]->estimatedTraffic)->toBe(4200.75)
        ->and($response->taskId)->toBe('competitors-task');

    Http::assertSent(fn (Request $request): bool => $request->data() === [[
        'target' => 'example.com',
        'location_code' => 2826,
        'language_code' => 'en',
        'item_types' => ['organic'],
        'exclude_top_domains' => true,
        'max_rank_group' => 100,
        'limit' => 25,
        'order_by' => ['intersections,desc'],
    ]]);
});

test('it handles a domain with no organic competitors', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response([
        'status_code' => 20000,
        'tasks' => [[
            'id' => 'empty-competitors-task',
            'status_code' => 20000,
            'cost' => 0.0101,
            'result_count' => 1,
            'result' => [['items_count' => 0, 'items' => []]],
        ]],
    ])]);

    expect(app(CompetitorsService::class)->forDomain('example.com', 2826, 'en')->competitors)->toBeEmpty();
});

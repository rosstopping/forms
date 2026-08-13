<?php

use App\Services\DataForSEO\RankedKeywordsService;
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
        'ranked_keywords_limit' => 500,
    ]);
    Http::preventStrayRequests();
});

test('it requests ranked organic keywords using the configured cost limit', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response([
        'status_code' => 20000,
        'tasks' => [[
            'id' => 'keyword-task',
            'status_code' => 20000,
            'cost' => 0.0103,
            'result_count' => 1,
            'result' => [[
                'target' => 'example.com',
                'items_count' => 1,
                'items' => [[
                    'keyword_data' => ['keyword' => 'garden room', 'location_code' => 2826, 'language_code' => 'en'],
                    'ranked_serp_element' => ['serp_item' => ['rank_group' => 12, 'rank_absolute' => 16, 'url' => 'https://example.com/garden-room']],
                ]],
            ]],
        ]],
    ])]);

    $response = app(RankedKeywordsService::class)->forDomain('example.com', 2826, 'en');

    expect($response->keywords)->toHaveCount(1)
        ->and($response->keywords[0]->keyword)->toBe('garden room')
        ->and($response->keywords[0]->position)->toBe(12);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.dataforseo.test/v3/dataforseo_labs/google/ranked_keywords/live'
            && $request->data() === [[
                'target' => 'example.com',
                'location_code' => 2826,
                'language_code' => 'en',
                'item_types' => ['organic'],
                'filters' => ['ranked_serp_element.serp_item.rank_group', '<=', 100],
                'limit' => 500,
                'order_by' => [
                    'keyword_data.keyword_info.search_volume,desc',
                    'ranked_serp_element.serp_item.rank_group,asc',
                ],
            ]];
    });
});

test('it handles a domain with no ranking keywords', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response([
        'status_code' => 20000,
        'tasks' => [[
            'id' => 'empty-task',
            'status_code' => 20000,
            'cost' => 0.0101,
            'result_count' => 1,
            'result' => [['target' => 'offline-example.com', 'items_count' => 0, 'items' => []]],
        ]],
    ])]);

    $response = app(RankedKeywordsService::class)->forDomain('offline-example.com', 2826, 'en');

    expect($response->keywords)->toBeEmpty();
});

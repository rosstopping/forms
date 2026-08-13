<?php

use App\Services\DataForSEO\BacklinksService;
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
        'referring_domains_limit' => 250,
    ]);
    Http::preventStrayRequests();
});

test('it maps a backlink overview without retaining the provider payload', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response(backlinkTaskResponse([[
        'rank' => 42,
        'backlinks' => 872,
        'referring_domains' => 74,
        'referring_ips' => 68,
        'referring_subnets' => 61,
        'broken_backlinks' => 9,
    ]], 'overview-task'))]);

    $response = app(BacklinksService::class)->overview('example.com');

    expect($response->overview->backlinks)->toBe(872)
        ->and($response->overview->referringDomains)->toBe(74)
        ->and($response->overview->domainRank)->toBe(42)
        ->and($response->taskId)->toBe('overview-task');
});

test('it maps and deduplicates the configured referring domain sample', function (): void {
    Http::fake(['api.dataforseo.test/*' => Http::response(backlinkTaskResponse([[
        'items' => [
            ['domain' => 'Publisher.Example', 'rank' => 71, 'backlinks' => 14, 'first_seen' => '2024-01-10 12:00:00 +00:00', 'last_seen' => '2026-08-01 09:00:00 +00:00'],
            ['domain' => 'publisher.example', 'rank' => 70, 'backlinks' => 13],
            ['domain' => '', 'rank' => 12, 'backlinks' => 1],
        ],
    ]], 'domains-task'))]);

    $response = app(BacklinksService::class)->referringDomains('example.com');

    expect($response->domains)->toHaveCount(1)
        ->and($response->domains[0]->domain)->toBe('publisher.example')
        ->and($response->domains[0]->domainRank)->toBe(71)
        ->and($response->domains[0]->backlinksCount)->toBe(14)
        ->and($response->domains[0]->firstSeen?->toDateString())->toBe('2024-01-10');

    Http::assertSent(fn (Request $request): bool => $request->data() === [[
        'target' => 'example.com',
        'include_subdomains' => true,
        'backlinks_status_type' => 'all',
        'rank_scale' => 'one_hundred',
        'limit' => 250,
        'order_by' => ['rank,desc'],
    ]]);
});

/** @param array<int, array<string, mixed>> $result @return array<string, mixed> */
function backlinkTaskResponse(array $result, string $taskId): array
{
    return ['status_code' => 20000, 'tasks' => [[
        'id' => $taskId,
        'status_code' => 20000,
        'cost' => 0.02,
        'result_count' => count($result),
        'result' => $result,
    ]]];
}

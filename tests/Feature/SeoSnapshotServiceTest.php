<?php

use App\Models\SeoSnapshot;
use App\Models\Website;
use App\Services\SeoIntelligence\SeoSnapshotService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.dataforseo', [
        'login' => 'api-login',
        'password' => 'api-password',
        'api_url' => 'https://api.dataforseo.test/v3',
        'connect_timeout' => 1,
        'timeout' => 2,
        'ranked_keywords_limit' => 500,
        'referring_domains_limit' => 250,
    ]);
    Http::preventStrayRequests();
});

test('it persists domain metrics and ranked keywords as a historical snapshot', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'offline-example.com', 'is_primary' => true]);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'domain_rank_overview')) {
            return Http::response(dataForSEOTaskResponse(domainOverviewResult(), 0.0101, 'overview-task'));
        }

        if (str_contains($request->url(), 'ranked_keywords')) {
            return Http::response(dataForSEOTaskResponse(rankedKeywordsResult(), 0.0103, 'keywords-task'));
        }

        if (str_contains($request->url(), 'backlinks/summary')) {
            return Http::response(dataForSEOTaskResponse(backlinkOverviewResult(), 0.0105, 'backlinks-task'));
        }

        return Http::response(dataForSEOTaskResponse(referringDomainsResult(), 0.0107, 'referring-domains-task'));
    });

    $snapshot = app(SeoSnapshotService::class)->create($website, 2826, 'en');

    expect($snapshot->status)->toBe(SeoSnapshot::STATUS_COMPLETED)
        ->and($snapshot->domain)->toBe('offline-example.com')
        ->and($snapshot->organic_keywords)->toBe(139)
        ->and($snapshot->estimated_organic_traffic)->toBe('720.2500')
        ->and($snapshot->top_3_keywords)->toBe(10)
        ->and($snapshot->top_10_keywords)->toBe(18)
        ->and($snapshot->top_20_keywords)->toBe(30)
        ->and($snapshot->top_100_keywords)->toBe(55)
        ->and($snapshot->metadata['data_source'])->toBe('third_party_estimate')
        ->and($snapshot->keywords)->toHaveCount(1)
        ->and($snapshot->keywords->first()->keyword)->toBe('garden rooms doncaster')
        ->and($snapshot->keywords->first()->position)->toBe(12)
        ->and($snapshot->backlinks)->toBe(872)
        ->and($snapshot->referring_domains)->toBe(74)
        ->and($snapshot->domain_rank)->toBe(42)
        ->and($snapshot->referringDomains)->toHaveCount(2)
        ->and($snapshot->referringDomains->pluck('domain')->all())->toContain('publisher.example')
        ->and($snapshot->apiUsages()->sum('cost'))->toEqual(0.0416)
        ->and($snapshot->apiUsages()->pluck('provider_task_id')->all())->toBe(['overview-task', 'keywords-task', 'backlinks-task', 'referring-domains-task']);

    Http::assertSentCount(4);
});

test('it stores an empty keyword dataset without inventing keyword rows', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'no-rankings.example', 'is_primary' => true]);
    Http::fake(function (Request $request) {
        $result = match (true) {
            str_contains($request->url(), 'domain_rank_overview') => [['target' => 'no-rankings.example', 'items' => []]],
            str_contains($request->url(), 'ranked_keywords') => [['target' => 'no-rankings.example', 'items_count' => 0, 'items' => []]],
            str_contains($request->url(), 'backlinks/summary') => [['target' => 'no-rankings.example', 'backlinks' => 0, 'referring_domains' => 0]],
            default => [['target' => 'no-rankings.example', 'items_count' => 0, 'items' => []]],
        };

        return Http::response(dataForSEOTaskResponse($result, 0.01, 'empty-task'));
    });

    $snapshot = app(SeoSnapshotService::class)->create($website);

    expect($snapshot->organic_keywords)->toBe(0)
        ->and($snapshot->estimated_organic_traffic)->toBe('0.0000')
        ->and($snapshot->keywords)->toBeEmpty();
});

test('it prevents duplicate provider items within a snapshot', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $keywords = rankedKeywordsResult();
    $keywords[0]['items'][] = $keywords[0]['items'][0];
    Http::fake(function (Request $request) use ($keywords) {
        $result = match (true) {
            str_contains($request->url(), 'domain_rank_overview') => domainOverviewResult(),
            str_contains($request->url(), 'ranked_keywords') => $keywords,
            str_contains($request->url(), 'backlinks/summary') => backlinkOverviewResult(),
            default => referringDomainsResult(),
        };

        return Http::response(dataForSEOTaskResponse($result, 0.01, 'task'));
    });

    $snapshot = app(SeoSnapshotService::class)->create($website);

    expect($snapshot->keywords)->toHaveCount(1);
});

test('it retains successful keyword data when backlink datasets fail', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'partial.example', 'is_primary' => true]);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'domain_rank_overview')) {
            return Http::response(dataForSEOTaskResponse(domainOverviewResult(), 0.01, 'overview-task'));
        }

        if (str_contains($request->url(), 'ranked_keywords')) {
            return Http::response(dataForSEOTaskResponse(rankedKeywordsResult(), 0.01, 'keywords-task'));
        }

        return Http::response([
            'status_code' => 20000,
            'tasks' => [['id' => 'failed-task', 'status_code' => 50000, 'status_message' => 'Internal Error', 'cost' => 0, 'result_count' => 0, 'result' => null]],
        ]);
    });

    $snapshot = app(SeoSnapshotService::class)->create($website);

    expect($snapshot->status)->toBe(SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS)
        ->and($snapshot->keywords)->toHaveCount(1)
        ->and($snapshot->errors)->toHaveKeys(['backlink_overview', 'referring_domains'])
        ->and($snapshot->apiUsages)->toHaveCount(2);
});

test('it does not repurchase core datasets when resuming an interrupted snapshot', function (): void {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'resume.example', 'is_primary' => true]);
    $snapshot = SeoSnapshot::factory()->for($website)->create([
        'status' => SeoSnapshot::STATUS_PROCESSING,
        'domain' => 'resume.example',
        'metadata' => ['data_source' => 'third_party_estimate', 'datasets' => ['domain_overview', 'ranked_keywords']],
        'completed_at' => null,
    ]);
    Http::fake(function (Request $request) {
        $result = str_contains($request->url(), 'backlinks/summary') ? backlinkOverviewResult() : referringDomainsResult();

        return Http::response(dataForSEOTaskResponse($result, 0.01, 'resume-task'));
    });

    $result = app(SeoSnapshotService::class)->process($snapshot);

    expect($result->status)->toBe(SeoSnapshot::STATUS_COMPLETED)
        ->and($result->backlinks)->toBe(872)
        ->and($result->referringDomains)->toHaveCount(2)
        ->and($result->apiUsages)->toHaveCount(2);

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'dataforseo_labs'));
});

test('it requires a stored domain before spending provider credits', function (): void {
    $website = Website::factory()->create();

    expect(fn () => app(SeoSnapshotService::class)->create($website))
        ->toThrow(InvalidArgumentException::class, 'does not have a domain');

    Http::assertNothingSent();
});

/** @param array<int, array<string, mixed>> $result @return array<string, mixed> */
function dataForSEOTaskResponse(array $result, float $cost, string $taskId): array
{
    return ['status_code' => 20000, 'tasks' => [[
        'id' => $taskId,
        'status_code' => 20000,
        'cost' => $cost,
        'result_count' => count($result),
        'result' => $result,
    ]]];
}

/** @return array<int, array<string, mixed>> */
function domainOverviewResult(): array
{
    return [['target' => 'offline-example.com', 'items' => [['metrics' => ['organic' => [
        'pos_1' => 4, 'pos_2_3' => 6, 'pos_4_10' => 8, 'pos_11_20' => 12,
        'pos_21_30' => 7, 'pos_31_40' => 5, 'pos_41_50' => 4, 'pos_51_60' => 3,
        'pos_61_70' => 2, 'pos_71_80' => 2, 'pos_81_90' => 1, 'pos_91_100' => 1,
        'count' => 139, 'etv' => 720.25,
    ]]]]]];
}

/** @return array<int, array<string, mixed>> */
function rankedKeywordsResult(): array
{
    return [['target' => 'offline-example.com', 'items_count' => 1, 'items' => [[
        'keyword_data' => [
            'keyword' => 'garden rooms doncaster', 'location_code' => 2826, 'language_code' => 'en',
            'keyword_info' => ['search_volume' => 390, 'cpc' => 2.75, 'competition' => 0.42, 'competition_level' => 'MEDIUM'],
            'keyword_properties' => ['keyword_difficulty' => 31],
            'search_intent_info' => ['main_intent' => 'commercial'],
        ],
        'ranked_serp_element' => ['serp_item' => [
            'rank_group' => 12, 'rank_absolute' => 16, 'url' => 'https://offline-example.com/garden-rooms', 'etv' => 18.4,
            'rank_changes' => ['previous_rank_group' => 15, 'previous_rank_absolute' => 19],
        ]],
    ]]]];
}

/** @return array<int, array<string, mixed>> */
function backlinkOverviewResult(): array
{
    return [[
        'target' => 'offline-example.com',
        'rank' => 42,
        'backlinks' => 872,
        'referring_domains' => 74,
        'referring_ips' => 68,
        'referring_subnets' => 61,
        'broken_backlinks' => 9,
    ]];
}

/** @return array<int, array<string, mixed>> */
function referringDomainsResult(): array
{
    return [['target' => 'offline-example.com', 'items_count' => 2, 'items' => [
        ['domain' => 'publisher.example', 'rank' => 71, 'backlinks' => 14, 'first_seen' => '2024-01-10 12:00:00 +00:00', 'last_seen' => '2026-08-01 09:00:00 +00:00'],
        ['domain' => 'directory.example', 'rank' => 39, 'backlinks' => 6, 'first_seen' => 'invalid-date', 'last_seen' => null],
    ]]];
}

<?php

use App\Jobs\BackfillSeoHistory;
use App\Jobs\SyncSearchConsoleHistory;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use App\Services\SearchConsoleHistoryStore;
use App\Services\SeoIntelligence\SeoHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

test('historical rank overview is persisted as monthly seo snapshots', function () {
    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $client = $this->mock(DataForSEOClient::class);
    $client->shouldReceive('post')->once()->andReturn(new DataForSEOResponse('history', [[
        'items' => [[
            'year' => 2025,
            'month' => 6,
            'metrics' => ['organic' => ['pos_1' => 2, 'pos_2_3' => 3, 'pos_4_10' => 5, 'pos_11_20' => 10, 'count' => 100, 'etv' => 432.5, 'is_up' => 8]],
        ]],
    ]], 0.1, 1, 'task'));

    expect((new SeoHistoryService($client))->backfill($website))->toBe(1);

    $snapshot = $website->seoSnapshots()->sole();
    expect($snapshot->snapshot_date->toDateString())->toBe('2025-06-30')
        ->and($snapshot->top_3_keywords)->toBe(5)
        ->and($snapshot->top_10_keywords)->toBe(10)
        ->and($snapshot->top_20_keywords)->toBe(20)
        ->and($snapshot->organic_keywords)->toBe(100)
        ->and((float) $snapshot->estimated_organic_traffic)->toBe(432.5)
        ->and($snapshot->metadata['historical'])->toBeTrue()
        ->and($website->fresh()->seo_history_backfilled_at)->not->toBeNull();
});

test('automatic weekly snapshots can be enabled and disabled', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)->put(route('admin.seo-snapshot-settings.update', $website), ['seo_weekly_snapshots_enabled' => true])->assertRedirect();
    expect($website->fresh()->seo_weekly_snapshots_enabled)->toBeTrue();
    Queue::assertPushed(BackfillSeoHistory::class);

    $this->actingAs($owner)->put(route('admin.seo-snapshot-settings.update', $website), ['seo_weekly_snapshots_enabled' => false])->assertRedirect();
    expect($website->fresh()->seo_weekly_snapshots_enabled)->toBeFalse();
});

test('manual intelligence refresh backfills history while weekly snapshots are disabled', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create([
        'seo_weekly_snapshots_enabled' => false,
        'seo_history_backfilled_at' => null,
    ]);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    $this->actingAs($owner)->post(route('admin.seo-intelligence.store', $website))->assertRedirect();

    Queue::assertPushed(BackfillSeoHistory::class, fn (BackfillSeoHistory $job): bool => $job->website->is($website));
});

test('historical backfill runs independently of the weekly snapshot setting', function () {
    $website = Website::factory()->create([
        'seo_weekly_snapshots_enabled' => false,
        'seo_history_backfilled_at' => null,
    ]);
    $history = $this->mock(SeoHistoryService::class);
    $history->shouldReceive('backfill')->once()->with($website)->andReturn(12);

    (new BackfillSeoHistory($website))->handle($history);
});

test('search console daily performance is aggregated into monthly history', function () {
    config(['services.google.search_console_url' => 'https://search-console.test']);
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $oauth = $this->mock(GoogleOAuthClient::class);
    $oauth->shouldReceive('accessToken')->once()->andReturn('token');
    Http::fake(['search-console.test/*' => Http::response(['rows' => [
        ['keys' => ['2026-06-01'], 'clicks' => 10, 'impressions' => 100, 'ctr' => .1, 'position' => 4],
        ['keys' => ['2026-06-02'], 'clicks' => 20, 'impressions' => 300, 'ctr' => .0667, 'position' => 8],
        ['keys' => ['2026-07-01'], 'clicks' => 40, 'impressions' => 500, 'ctr' => .08, 'position' => 3],
    ]])]);

    $history = (new SearchConsoleClient($oauth))->monthlyPerformance($connection);

    expect($history)->toHaveCount(2)
        ->and($history[0]['month'])->toBe('2026-06')
        ->and($history[0]['clicks'])->toBe(30.0)
        ->and($history[0]['impressions'])->toBe(400.0)
        ->and($history[0]['ctr'])->toBe(.075)
        ->and($history[0]['position'])->toBe(7.0);
});

test('search console query history uses an exact query filter', function () {
    config(['services.google.search_console_url' => 'https://search-console.test']);
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $oauth = $this->mock(GoogleOAuthClient::class);
    $oauth->shouldReceive('accessToken')->once()->andReturn('token');
    Http::fake(['search-console.test/*' => Http::response(['rows' => [
        ['keys' => ['2026-07-01'], 'clicks' => 4, 'impressions' => 100, 'ctr' => .04, 'position' => 12],
        ['keys' => ['2026-07-02'], 'clicks' => 6, 'impressions' => 100, 'ctr' => .06, 'position' => 8],
    ]])]);

    $history = (new SearchConsoleClient($oauth))->monthlyPerformanceForQuery($connection, 'luxury train journeys');

    expect($history)->toHaveCount(1)
        ->and($history[0]['clicks'])->toBe(10.0)
        ->and($history[0]['impressions'])->toBe(200.0)
        ->and($history[0]['position'])->toBe(10.0);

    Http::assertSent(fn ($request): bool => data_get($request->data(), 'dimensionFilterGroups.0.filters.0') === [
        'dimension' => 'query',
        'operator' => 'equals',
        'expression' => 'luxury train journeys',
    ]);
});

test('search console history remains stored after it falls outside the provider window', function () {
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    SearchConsoleMetric::factory()->for($connection->website)->for($connection, 'connection')->create([
        'property_url' => $connection->property_url,
        'property_hash' => hash('sha256', $connection->property_url),
        'month' => '2024-01-01',
        'clicks' => 40,
        'impressions' => 800,
        'ctr' => .05,
        'position' => 12,
    ]);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('monthlyPerformance')->once()->andReturn([
        ['month' => '2026-07', 'clicks' => 100.0, 'impressions' => 2000.0, 'ctr' => .05, 'position' => 7.1],
    ]);

    $history = (new SearchConsoleHistoryStore($client))->syncSite($connection);

    expect($history)->toHaveCount(2)
        ->and($history[0]['month'])->toBe('2024-01')
        ->and($history[1]['month'])->toBe('2026-07')
        ->and($connection->metrics()->count())->toBe(2);
});

test('search console history refresh updates a month instead of duplicating it', function () {
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('monthlyPerformance')->twice()->andReturn(
        [['month' => '2026-07', 'clicks' => 100.0, 'impressions' => 2000.0, 'ctr' => .05, 'position' => 7.1]],
        [['month' => '2026-07', 'clicks' => 125.0, 'impressions' => 2500.0, 'ctr' => .05, 'position' => 6.4]],
    );
    $store = new SearchConsoleHistoryStore($client);

    $store->syncSite($connection);
    $history = $store->syncSite($connection);

    expect($connection->metrics()->count())->toBe(1)
        ->and($history[0]['clicks'])->toBe(125.0)
        ->and($history[0]['position'])->toBe(6.4);
});

test('stored search console history survives disconnecting the Google account', function () {
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $metric = SearchConsoleMetric::factory()->for($connection->website)->for($connection, 'connection')->create([
        'property_url' => $connection->property_url,
        'property_hash' => hash('sha256', $connection->property_url),
    ]);

    $connection->delete();

    expect($metric->fresh())->not->toBeNull()
        ->and($metric->fresh()->search_console_connection_id)->toBeNull();
});

test('viewed search console queries are stored as tracked histories', function () {
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('monthlyPerformanceForQuery')->once()->with($connection, 'luxury trains')->andReturn([
        ['month' => '2026-07', 'clicks' => 12.0, 'impressions' => 240.0, 'ctr' => .05, 'position' => 8.2],
    ]);

    (new SearchConsoleHistoryStore($client))->syncQuery($connection, 'luxury trains');

    $metric = $connection->metrics()->firstOrFail();
    expect($metric->query)->toBe('luxury trains')
        ->and($metric->dimension_key)->toBe(hash('sha256', 'query:luxury trains'));
});

test('weekly history sync stores a broad monthly query sample in bounded requests', function () {
    $this->travelTo('2026-08-16 12:00:00');
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('monthlyPerformance')->once()->with($connection)->andReturn([]);
    $client->shouldReceive('queryPerformanceForPeriod')->times(17)
        ->withArgs(fn (SearchConsoleConnection $givenConnection, $start, $end, int $limit): bool => $givenConnection->is($connection) && $start->isStartOfMonth() && $end->gte($start) && $limit === 1000)
        ->andReturnUsing(fn (SearchConsoleConnection $givenConnection, $start): array => match ($start->format('Y-m')) {
            '2025-05' => [['query' => 'northern belle', 'clicks' => 50.0, 'impressions' => 800.0, 'ctr' => .0625, 'position' => 18.0]],
            '2026-08' => [['query' => 'northern belle', 'clicks' => 120.0, 'impressions' => 1000.0, 'ctr' => .12, 'position' => 4.5]],
            default => [],
        });

    (new SearchConsoleHistoryStore($client))->syncTracked($connection);

    expect($connection->metrics()->whereNotNull('query')->pluck('query')->unique()->values()->all())
        ->toBe(['northern belle'])
        ->and($connection->metrics()->whereNotNull('query')->count())->toBe(2)
        ->and($connection->metrics()->where('month', '2025-05-01')->value('position'))->toBe(18.0);
});

test('search console history job syncs its search console connection', function () {
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);
    $history = $this->mock(SearchConsoleHistoryStore::class);
    $history->shouldReceive('syncTracked')->once()->with($connection);

    (new SyncSearchConsoleHistory($connection))->handle($history);
});

test('search console history command queues connected properties', function () {
    Queue::fake();
    $connection = SearchConsoleConnection::factory()->create(['property_url' => 'sc-domain:example.com']);

    $this->artisan('search-console:sync-history')
        ->expectsOutput('Queued 1 Search Console history sync(s).')
        ->assertSuccessful();

    Queue::assertPushed(
        SyncSearchConsoleHistory::class,
        fn (SyncSearchConsoleHistory $job): bool => $job->searchConsoleConnection->is($connection),
    );
});

test('search console history synchronisation is scheduled weekly', function () {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command, 'search-console:sync-history'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 4 * * 1');
});

test('progress chart exposes every period for pointer and keyboard inspection', function () {
    $html = Blade::render(
        '<x-progress-chart title="Organic clicks" description="Monthly clicks." :points="$points" value-key="clicks" />',
        ['points' => [
            ['month' => '2026-05', 'clicks' => 10],
            ['month' => '2026-06', 'clicks' => 25],
            ['month' => '2026-07', 'clicks' => 20],
        ]],
    );

    expect($html)
        ->toContain('data-progress-chart')
        ->toContain('data-chart-tooltip')
        ->toContain('aria-label="May 2026: 10"')
        ->toContain('aria-label="Jun 2026: 25"')
        ->toContain('aria-label="Jul 2026: 20"')
        ->and(substr_count($html, 'data-chart-point'))
        ->toBe(3);
});

test('progress chart accepts eloquent snapshot observations', function () {
    $website = Website::factory()->create();
    $snapshots = collect([
        SeoSnapshot::factory()->for($website)->make([
            'snapshot_date' => '2026-06-30',
            'estimated_organic_traffic' => 125.5,
        ]),
        SeoSnapshot::factory()->for($website)->make([
            'snapshot_date' => '2026-07-31',
            'estimated_organic_traffic' => 150.5,
        ]),
    ]);

    $html = Blade::render(
        '<x-progress-chart title="Traffic" description="Estimated traffic." :points="$points" value-key="estimated_organic_traffic" format="traffic" />',
        ['points' => $snapshots],
    );

    expect($html)
        ->toContain('aria-label="30 Jun 2026: ~126"')
        ->toContain('aria-label="31 Jul 2026: ~151"')
        ->and(substr_count($html, 'data-chart-point'))
        ->toBe(2);
});

test('comparison chart renders two labelled series without horizontal overflow', function () {
    $html = Blade::render(
        '<x-comparison-chart title="Clicks and impressions" description="Monthly performance." :points="$points" first-key="clicks" first-label="Clicks" second-key="impressions" second-label="Impressions" />',
        ['points' => [
            ['month' => '2026-06', 'clicks' => 12, 'impressions' => 240],
            ['month' => '2026-07', 'clicks' => 18, 'impressions' => 360],
        ]],
    );

    expect($html)
        ->toContain('data-comparison-chart')
        ->toContain('Clicks: 12 · Impressions: 240')
        ->toContain('Clicks: 18 · Impressions: 360')
        ->toContain('stroke-teal-700')
        ->toContain('stroke-violet-600')
        ->not->toContain('overflow-x-auto')
        ->not->toContain('min-w-xl')
        ->and(substr_count($html, '<polyline'))
        ->toBe(2);
});

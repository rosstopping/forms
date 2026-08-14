<?php

use App\Jobs\BackfillSeoHistory;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\GoogleOAuthClient;
use App\Services\SearchConsoleClient;
use App\Services\SeoIntelligence\SeoHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

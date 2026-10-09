<?php

use App\Models\ContentRequest;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use App\Models\SeoImpact;
use App\Models\SeoImpactReview;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\SeoWin;
use App\Models\User;
use App\Models\Website;
use App\Services\ReportingPeriod;
use App\Services\SeoPerformanceSignals;
use App\Services\SeoProgressTimeline;
use App\Services\SeoWinDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
    Http::preventStrayRequests();
    Queue::fake();
    Mail::fake();
    $this->customer = User::factory()->create();
    $this->website = Website::factory()->for($this->customer, 'owner')->create();
    $this->customer->update(['current_website_id' => $this->website->id]);
    $this->connection = SearchConsoleConnection::factory()->for($this->website)->create();
});

function signalDays(SearchConsoleConnection $connection, int $current = 30, int $previous = 10, int $shift = 0): void
{
    $end = ReportingPeriod::cutoff()->subDays($shift);
    for ($day = 0; $day < 59; $day++) {
        SearchConsoleDailyMetric::factory()->create(['search_console_connection_id' => $connection->id, 'date' => $end->subDays($day),
            'clicks' => $day < 28 ? $current : $previous, 'impressions' => 1000]);
    }
}

function signalReview(Website $website, SearchConsoleConnection $connection, array $changes = []): SeoImpact
{
    $request = ContentRequest::factory()->for($website)->create(['work_type' => 'new_article']);
    $impact = SeoImpact::factory()->for($website)->create(['content_request_id' => $request->id, 'property_url' => $connection->property_url,
        'live_at' => now()->subDays(40), 'verified_at' => now()->subDays(39), 'verification_status' => 'checked', ...$changes]);
    $start = ReportingPeriod::cutoff()->subDays(27)->toDateString();
    $end = ReportingPeriod::cutoff()->toDateString();
    SeoImpactReview::factory()->for($impact, 'impact')->create(['period_start' => $start, 'period_end' => $end,
        'measurement' => ['source' => 'search_console', 'complete' => true, 'window_days' => 28, 'start' => $start, 'end' => $end,
            'target' => ['totals' => ['reported_days' => 28, 'clicks' => 20, 'impressions' => 400]]]]);

    return $impact;
}

test('traffic wins use complete equal finalized periods and deduplicate rechecks and dismissed records', function (): void {
    signalDays($this->connection);
    app(SeoPerformanceSignals::class)->detect($this->website);
    $win = SeoWin::where('rule', 'traffic_growth')->sole();
    expect($win->evidence['current']['clicks'])->toBe(840)->and($win->evidence['previous']['clicks'])->toBe(280)
        ->and($win->evidence['confirmation']['end'])->toBe(ReportingPeriod::cutoff()->subDays(3)->toDateString());
    $count = SeoWin::count();
    $win->update(['dismissed_at' => now()]);
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::count())->toBe($count);
    Http::assertNothingSent();
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('missing unknown stale or different property data cannot produce traffic wins', function (string $failure): void {
    signalDays($this->connection, shift: $failure === 'stale' ? 20 : 0);
    match ($failure) {
        'missing' => SearchConsoleDailyMetric::where('website_id', $this->website->id)->oldest('date')->first()->delete(),
        'unknown' => SearchConsoleDailyMetric::where('website_id', $this->website->id)->update(['data_status' => 'no_data']),
        'property' => $this->connection->update(['property_url' => 'sc-domain:changed.example.com']),
        'denied' => $this->connection->forceFill(['access_denied_at' => now()])->save(),
        default => null,
    };
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
})->with(['missing', 'unknown', 'stale', 'property', 'denied']);

test('small movements and zero baselines do not generate traffic growth celebrations', function (int $current, int $previous): void {
    signalDays($this->connection, $current, $previous);
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::where('rule', 'traffic_growth')->count())->toBe(0);
})->with([[11, 10], [1, 0], [3, 2]]);

test('meaningful year on year improvements require complete equivalent evidence', function (): void {
    signalDays($this->connection, 30, 30);
    $end = ReportingPeriod::cutoff()->subYearNoOverflow();
    for ($day = 0; $day < 31; $day++) {
        SearchConsoleDailyMetric::factory()->create(['search_console_connection_id' => $this->connection->id, 'date' => $end->subDays($day), 'clicks' => 10]);
    }
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::where('rule', 'traffic_year_growth')->sole()->evidence['comparison'])->toBe('year');
});

test('traffic declines remain alerts and never create executable content', function (): void {
    signalDays($this->connection, 10, 30);
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::where('category', 'alert')->sole()->rule)->toBe('traffic_decline')->and(ContentRequest::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('content traction links verified publication page keywords and saved review without paid calls', function (): void {
    $impact = signalReview($this->website, $this->connection);
    app(SeoPerformanceSignals::class)->detect($this->website);
    $win = SeoWin::sole();
    expect($win->rule)->toBe('content_traction')->and($win->seo_impact_id)->toBe($impact->id)
        ->and($win->evidence['urls'])->toBe($impact->target_urls)->and($win->evidence['keywords'])->toBe($impact->target_queries)
        ->and($win->client_draft)->toContain('does not prove');
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::count())->toBe(1);
    Http::assertNothingSent();
});

test('merged unverified incomplete or old-property content cannot be celebrated as published results', function (string $failure): void {
    $impact = signalReview($this->website, $this->connection);
    match ($failure) {
        'unverified' => $impact->update(['verification_status' => 'pending']),
        'property' => $impact->update(['property_url' => 'sc-domain:old.example.com']),
        'incomplete' => $impact->reviews()->first()->update(['measurement' => ['complete' => false]]),
        'future' => $impact->reviews()->first()->update(['period_end' => today()]),
    };
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
})->with(['unverified', 'property', 'incomplete', 'future']);

test('ranking alerts and opportunities use independent persisted checks without queueing work', function (array $positions, string $category): void {
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['priority' => 'high']);
    foreach ($positions as $index => $position) {
        SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['seo_target_keyword_id' => $target->id,
            'website_id' => $this->website->id, 'position' => $position, 'observed_at' => now()->subDays((2 - $index) * 7),
            'provider' => 'dataforseo', 'location_code' => config('services.dataforseo.location_code'), 'language_code' => config('services.dataforseo.language_code')]);
    }
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::sole()->category)->toBe($category)->and(ContentRequest::count())->toBe(0);
})->with([[[5, 18, 19], 'alert'], [[22, 16, 15], 'opportunity']]);

test('customer timeline shows only reviewed shared wins and verified publications from the selected site', function (): void {
    $impact = signalReview($this->website, $this->connection);
    SeoWin::factory()->for($this->website)->create(['title' => 'Approved milestone', 'approved_at' => now(), 'shared_at' => now(), 'shared_text' => 'Friendly approved update']);
    SeoWin::factory()->for($this->website)->create(['title' => 'Private draft']);
    SeoWin::factory()->for($this->website)->create(['category' => 'alert', 'title' => 'Private alert', 'approved_at' => now(), 'shared_at' => now()]);
    SeoWin::factory()->create(['title' => 'Foreign site win', 'approved_at' => now(), 'shared_at' => now()]);
    $this->actingAs($this->customer)->get(route('admin.dashboard'))->assertSuccessful()->assertSee('SEO progress timeline')
        ->assertSee('Approved milestone')->assertSee('Friendly approved update')->assertSee($impact->title)
        ->assertDontSee('Private draft')->assertDontSee('Private alert')->assertDontSee('Foreign site win');
    expect(app(SeoProgressTimeline::class)->forWebsite($this->website)->pluck('type')->all())->toContain('SEO milestone', 'Published or updated content');
    Http::assertNothingSent();
});

test('admin inbox separates categories and preserves manual approval workflow', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $alert = SeoWin::factory()->for($this->website)->create(['category' => 'alert', 'title' => 'Review traffic drop']);
    SeoWin::factory()->for($this->website)->create(['title' => 'Positive milestone']);
    $this->actingAs($admin)->get(route('admin.overview', ['hub' => 'wins', 'signal_category' => 'alert']))->assertSuccessful()
        ->assertSee('Review traffic drop')->assertDontSee('Positive milestone');
    $this->patch(route('admin.seo-wins.update', $alert), ['action' => 'approve', 'client_draft' => 'Review the evidence.'])
        ->assertRedirect(route('admin.overview', ['hub' => 'wins', 'site_id' => $this->website->id, 'signal_category' => 'alert']));
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('click milestones are deduplicated and retain the actual rolling-window evidence', function (): void {
    signalDays($this->connection);
    app(SeoPerformanceSignals::class)->detect($this->website);
    $win = SeoWin::where('rule', 'traffic_milestone')->sole();
    expect($win->evidence['threshold'])->toBe(500)->and($win->evidence['current']['clicks'])->toBe(840)
        ->and($win->evidence['current']['days'])->toBe(28);
    $this->travel(1)->days();
    SearchConsoleDailyMetric::factory()->create(['search_console_connection_id' => $this->connection->id,
        'date' => ReportingPeriod::cutoff(), 'clicks' => 30]);
    app(SeoPerformanceSignals::class)->detect($this->website->fresh());
    expect(SeoWin::where('rule', 'traffic_milestone')->count())->toBe(1)->and(SeoWin::where('rule', 'traffic_growth')->count())->toBe(1);
});

test('existing-page content wins require materially improved comparable checkpoint measurements', function (): void {
    $impact = signalReview($this->website, $this->connection);
    $impact->contentRequest->update(['work_type' => 'optimisation']);
    $review = $impact->reviews()->first();
    $review->update(['outcome' => 'improved', 'baseline' => ['complete' => true, 'window_days' => 28,
        'target' => ['totals' => ['clicks' => 5, 'impressions' => 200, 'reported_days' => 28]]]]);
    app(SeoPerformanceSignals::class)->detect($this->website);
    expect(SeoWin::sole()->rule)->toBe('content_growth');
});

test('unverified merged work is never labelled as published in the timeline', function (): void {
    signalReview($this->website, $this->connection, ['verification_status' => 'pending']);
    expect(app(SeoProgressTimeline::class)->forWebsite($this->website)->where('type', 'Published or updated content'))->toHaveCount(0);
});

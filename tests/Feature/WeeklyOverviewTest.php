<?php

use App\Ai\Agents\WeeklyOverviewWriter;
use App\Jobs\SendWeeklyRankingReport;
use App\Mail\WeeklyRankingReport;
use App\Models\BusinessProfileConnection;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\Optimisation;
use App\Models\SearchConsoleConnection;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WeeklyReport;
use App\Services\BusinessProfileClient;
use App\Services\BusinessProfileOAuthClient;
use App\Services\GoogleOAuthClient;
use App\Services\RankingReportBuilder;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteMailRecipients;
use App\Services\WeeklyReportBuilder;
use App\Services\WeeklyReportGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    WeeklyOverviewWriter::fake(['Search visibility improved. Sitewell completed the recorded work.']);
    Http::preventStrayRequests();
});

test('weekly metrics handle zero baselines missing data and lower-is-better values', function (): void {
    $builder = app(WeeklyReportBuilder::class);
    expect($builder->metric('Clicks', 4, 3))->toMatchArray(['change' => 1.0, 'percent' => 33.3, 'direction' => 'positive'])
        ->and($builder->metric('Clicks', 4, 0))->toMatchArray(['change' => 4.0, 'percent' => null])
        ->and($builder->metric('Clicks', null, 3))->toMatchArray(['change' => null, 'direction' => 'neutral'])
        ->and($builder->metric('Position', 11, 18, true)['direction'])->toBe('positive')
        ->and($builder->metric('CTR', 0, 0)['direction'])->toBe('neutral');
});

test('weekly search movements ignore missing sampled rows and small changes', function (): void {
    $builder = app(WeeklyReportBuilder::class);
    $movements = $builder->searchMovements([
        ['key' => 'growing', 'clicks' => 10, 'impressions' => 150],
        ['key' => 'declining', 'clicks' => 2, 'impressions' => 50],
        ['key' => 'new sample', 'clicks' => 20, 'impressions' => 500],
        ['key' => 'noise', 'clicks' => 2, 'impressions' => 101],
    ], [
        ['key' => 'growing', 'clicks' => 5, 'impressions' => 100],
        ['key' => 'declining', 'clicks' => 8, 'impressions' => 100],
        ['key' => 'missing sample', 'clicks' => 20, 'impressions' => 500],
        ['key' => 'noise', 'clicks' => 2, 'impressions' => 100],
    ]);
    expect(array_column($movements['gaining'], 'key'))->toBe(['growing'])
        ->and(array_column($movements['losing'], 'key'))->toBe(['declining']);
});

test('weekly reports persist one snapshot and narrative even after underlying data changes', function (): void {
    $website = Website::factory()->create();
    $generator = app(WeeklyReportGenerator::class);
    $report = $generator->generate($website, today());
    $snapshot = $report->snapshot;
    WebsiteHealthReport::factory()->for($website)->create(['completed_at' => now()->subDay()]);
    $again = $generator->generate($website, today());

    expect($again->id)->toBe($report->id)
        ->and($again->snapshot)->toBe($snapshot)
        ->and($again->period_start->toDateString())->toBe('2026-09-07')
        ->and($again->period_end->toDateString())->toBe('2026-09-13')
        ->and($again->snapshot['reporting_period']['comparison_start'])->toBe('2026-08-31')
        ->and($again->snapshot)->not->toHaveKey('ai_visibility')
        ->and(WeeklyReport::count())->toBe(1);
    WeeklyOverviewWriter::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'reporting_period'));
});

test('rankings compare equivalent weeks in the configured market and preserve missing evidence', function (): void {
    $website = Website::factory()->create();
    foreach ([['gain', 18, 10], ['drop', 3, 11], ['noise', 8, 7], ['steady', 22, 22], ['not ranked', null, null], ['new rank', null, 19]] as [$term, $before, $after]) {
        $target = SeoTargetKeyword::factory()->for($website)->create(['term' => $term, 'created_at' => now()->subMonth()]);
        foreach ([['2026-09-03', $before], ['2026-09-10', $after]] as [$at, $position]) {
            SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'observed_at' => $at, 'position' => $position, 'status' => $position === null ? 'not_found' : 'ranked']);
        }
        SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'observed_at' => '2026-09-12', 'position' => 1, 'device' => 'mobile']);
        SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'observed_at' => '2026-09-13', 'position' => null, 'status' => 'failed']);
    }
    SeoTargetKeyword::factory()->for($website)->create(['created_at' => now()->subMonth()]);
    SeoTargetKeyword::factory()->for($website)->create(['created_at' => now()->subMonth(), 'archived_at' => '2026-09-01']);
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    $rankings = $report->snapshot['rankings'];
    expect($rankings)->toMatchArray(['total' => 7, 'improved' => 3, 'declined' => 1, 'unchanged' => 2, 'awaiting_comparison' => 1, 'ranking' => 5, 'positions_4_10' => 2, 'positions_11_20' => 2, 'outside_top_20' => 2, 'missing' => 1])
        ->and($rankings['largest_gain']['term'])->toBe('gain')
        ->and($rankings['largest_loss']['term'])->toBe('drop')
        ->and(collect($rankings['movements'])->firstWhere('term', 'gain')['crossings'])->toContain('Entered top 10')
        ->and(collect($rankings['movements'])->pluck('term')->all())->not->toContain('noise')
        ->and($rankings['improved_metric']['previous'])->toBeNull()
        ->and($report->recommended_priority['source'])->toBe('rankings');
});

test('audit resolution requires a passing check rather than a missing page', function (): void {
    $website = Website::factory()->create();
    $old = WebsiteHealthReport::factory()->for($website)->create(['completed_at' => '2026-09-03', 'checks' => [
        ['key' => 'metadata', 'label' => 'Missing metadata', 'status' => 'failed'],
        ['key' => 'indexing', 'label' => 'Indexing', 'status' => 'passed'],
    ]]);
    WebsiteHealthReportPage::factory()->for($old, 'report')->create(['url' => 'https://example.com/removed', 'checks' => [['key' => 'links', 'label' => 'Broken links', 'status' => 'failed']]]);
    WebsiteHealthReport::factory()->for($website)->create(['completed_at' => '2026-09-10', 'checks' => [
        ['key' => 'metadata', 'label' => 'Missing metadata', 'status' => 'passed'],
        ['key' => 'indexing', 'label' => 'Indexing', 'status' => 'failed', 'message' => 'Search engines are blocked.'],
    ]]);
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($report->snapshot['site_audit'])->toMatchArray(['resolved_issues' => 1, 'new_issues' => 1, 'important_issues' => 1])
        ->and($report->recommended_priority)->toMatchArray(['source' => 'site_audit', 'title' => 'Resolve Indexing']);
});

test('search uses separate lagged complete weeks and keeps site totals separate from samples', function (): void {
    $website = Website::factory()->create();
    SearchConsoleConnection::factory()->for($website)->create();
    $this->mock(SearchConsoleClient::class, function ($mock): void {
        $mock->shouldReceive('weeklyPerformance')->once()->withArgs(fn ($connection, $start, $end) => $start->toDateString() === '2026-09-05' && $end->toDateString() === '2026-09-11')->andReturn(['totals' => ['impressions' => 200, 'clicks' => 8, 'ctr' => 0.04, 'position' => 12], 'queries' => [['key' => 'boiler repair', 'impressions' => 100, 'clicks' => 1, 'ctr' => 0.01, 'position' => 11]], 'pages' => []]);
        $mock->shouldReceive('weeklyPerformance')->once()->withArgs(fn ($connection, $start, $end) => $start->toDateString() === '2026-08-29' && $end->toDateString() === '2026-09-04')->andReturn(['totals' => ['impressions' => 100, 'clicks' => 4, 'ctr' => 0.04, 'position' => 14], 'queries' => [], 'pages' => []]);
    });
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($report->snapshot['search_console']['metrics']['impressions'])->toMatchArray(['current' => 200, 'previous' => 100, 'percent' => 100])
        ->and($report->snapshot['search_console']['metrics']['ctr']['current'])->toEqual(4)
        ->and($report->recommended_priority['type'])->toBe('low_ctr');
});

test('business metrics require seven dates per metric and distinguish missing series from zero', function (): void {
    $series = ['multiDailyMetricTimeSeries' => [['dailyMetricTimeSeries' => [
        ['dailyMetric' => 'WEBSITE_CLICKS', 'timeSeries' => ['datedValues' => collect(range(7, 13))->map(fn ($day) => ['date' => ['year' => 2026, 'month' => 9, 'day' => $day], 'value' => $day === 7 ? '4' : '0'])->all()]],
        ['dailyMetric' => 'CALL_CLICKS', 'timeSeries' => ['datedValues' => [['date' => ['year' => 2026, 'month' => 9, 'day' => 7], 'value' => '2']]]],
    ]]]];
    $totals = app(WeeklyReportBuilder::class)->businessTotals($series, Carbon::parse('2026-09-07'), Carbon::parse('2026-09-13'));
    expect($totals['website_clicks'])->toBe(4.0)->and($totals['calls'])->toBeNull()->and($totals['impressions'])->toBeNull();
});

test('completed work excludes drafts unmerged content and work outside the period', function (): void {
    $website = Website::factory()->create();
    Optimisation::factory()->for($website)->create(['deployed_at' => '2026-09-09', 'status' => 'deployed', 'type' => 'meta_description']);
    Optimisation::factory()->for($website)->create(['deployed_at' => null, 'status' => 'draft']);
    Optimisation::factory()->for($website)->create(['deployed_at' => '2026-08-20', 'status' => 'deployed']);
    $plan = ContentPlan::factory()->for($website)->create();
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => 'pull_request_open', 'merged_at' => null]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => 'completed', 'merged_at' => '2026-09-11', 'scheduled_for' => '2026-09-11']);
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($report->snapshot['completed_work'])->toHaveCount(2)
        ->and(array_column($report->snapshot['completed_work'], 'source'))->toContain('optimisation', 'content_generation');
});

test('dashboard history is read only scoped and displays the exact saved email narrative', function (): void {
    $website = Website::factory()->create();
    $other = Website::factory()->create();
    $report = WeeklyReport::factory()->for($website)->create(['overview' => 'Saved weekly narrative with exact figures.']);
    $foreignReport = WeeklyReport::factory()->for($other)->create(['overview' => 'Private other website report.']);
    $this->actingAs($website->owner)->get(route('admin.weekly-overviews.show', [$website, 'weekly_report' => $report->id]))->assertSuccessful()->assertSee($report->overview)->assertSee('Previous overviews')->assertDontSee($foreignReport->overview);
    $this->get(route('admin.weekly-overviews.show', [$website, 'weekly_report' => $foreignReport->id]))->assertNotFound();
    $this->get(route('admin.weekly-overviews.show', $other))->assertForbidden();
    (new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website), $report))->assertSeeInHtml($report->overview)->assertSeeInHtml('Your week with Sitewell');
    WeeklyOverviewWriter::assertNeverPrompted();
    Http::assertNothingSent();
});

test('existing weekly email generates one report for all recipients and keeps dispatch dates across retries', function (): void {
    Mail::fake();
    $website = Website::factory()->create();
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => 'viewer']);
    $job = unserialize(serialize(new SendWeeklyRankingReport($website)));
    $this->travel(1)->days();
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    $report = WeeklyReport::sole();
    expect($report->period_end->toDateString())->toBe('2026-09-13');
    Mail::assertQueued(WeeklyRankingReport::class, fn ($mail) => $mail->hasTo($viewer->email) && $mail->weeklyOverview->id === $report->id);
    WeeklyOverviewWriter::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'reporting_period'));
});

test('AI failure saves a factual fallback so the existing weekly email can continue', function (): void {
    WeeklyOverviewWriter::fake(fn () => throw new RuntimeException('Provider unavailable'));
    $report = app(WeeklyReportGenerator::class)->generate(Website::factory()->create(), today());
    expect($report->generated_at)->not->toBeNull()->and($report->narrative_source)->toBe('deterministic')->and($report->overview)->toContain('No completed Sitewell work was recorded', 'not enough evidence');
});

test('search client requests final period totals and separate bounded samples', function (): void {
    $connection = SearchConsoleConnection::factory()->create();
    $this->mock(GoogleOAuthClient::class, fn ($mock) => $mock->shouldReceive('accessToken')->andReturn('test-token'));
    Http::fake(['*/searchAnalytics/query' => Http::response(['rows' => [['keys' => ['sample'], 'clicks' => 4, 'impressions' => 100, 'ctr' => 0.04, 'position' => 12]]])]);
    $result = app(SearchConsoleClient::class)->weeklyPerformance($connection, Carbon::parse('2026-09-05'), Carbon::parse('2026-09-11'));
    expect($result['totals']['impressions'])->toBe(100.0)->and($result['queries'][0]['key'])->toBe('sample');
    Http::assertSent(fn ($request) => $request['dimensions'] === [] && $request['dataState'] === 'final' && $request['startDate'] === '2026-09-05' && $request['endDate'] === '2026-09-11');
    Http::assertSent(fn ($request) => $request['dimensions'] === ['page'] && $request['rowLimit'] === 250);
    Http::assertSentCount(4);
});

test('search client suppresses incomplete periods instead of comparing partial totals', function (): void {
    $connection = SearchConsoleConnection::factory()->create();
    $this->mock(GoogleOAuthClient::class, fn ($mock) => $mock->shouldReceive('accessToken')->andReturn('test-token'));
    Http::fake(['*/searchAnalytics/query' => Http::response(['metadata' => ['first_incomplete_date' => '2026-09-11']])]);
    $result = app(SearchConsoleClient::class)->weeklyPerformance($connection, Carbon::parse('2026-09-05'), Carbon::parse('2026-09-11'));
    expect($result['totals'])->toBeNull()->and($result['queries'])->toBe([]);
    Http::assertSentCount(1);
});

test('business client reuses OAuth and requests all daily metrics and authoritative review totals', function (): void {
    $connection = BusinessProfileConnection::factory()->create(['account_name' => 'accounts/123', 'location_name' => 'accounts/123/locations/456']);
    $this->mock(BusinessProfileOAuthClient::class, fn ($mock) => $mock->shouldReceive('accessToken')->andReturn('test-token'));
    Http::fake(['businessprofileperformance.googleapis.com/*' => Http::response(['multiDailyMetricTimeSeries' => []]), '*/reviews*' => Http::response(['totalReviewCount' => 100, 'averageRating' => 4.9])]);
    $client = app(BusinessProfileClient::class);
    $client->weeklyPerformance($connection, Carbon::parse('2026-08-31'), Carbon::parse('2026-09-13'));
    expect($client->reviewSummary($connection))->toBe(['count' => 100, 'rating' => 4.9]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/locations/456:fetchMultiDailyMetricsTimeSeries?') && substr_count($request->url(), 'dailyMetrics=') === 7 && str_contains($request->url(), 'dailyRange.startDate.day=31'));
});

test('missing Google data does not stop generation or turn unavailable metrics into zeros', function (): void {
    $website = Website::factory()->create();
    SearchConsoleConnection::factory()->for($website)->create();
    BusinessProfileConnection::factory()->for($website)->create(['location_name' => 'locations/456']);
    $this->mock(SearchConsoleClient::class, fn ($mock) => $mock->shouldReceive('weeklyPerformance')->andThrow(new RuntimeException('Google unavailable')));
    $this->mock(BusinessProfileClient::class, function ($mock): void {
        $mock->shouldReceive('weeklyPerformance')->andThrow(new RuntimeException('Google unavailable'));
        $mock->shouldReceive('reviewSummary')->andReturn(['count' => 10, 'rating' => 5.0]);
    });
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($report->snapshot['search_console']['status'])->toBe('unavailable')
        ->and($report->snapshot['google_business']['reviews']['count']['current'])->toEqual(10)
        ->and($report->snapshot['metric_cards'][0]['current'])->toBeNull();
});

test('weekly dispatcher includes enabled health-only and Google Business websites', function (): void {
    Queue::fake();
    $health = Website::factory()->create(['weekly_ranking_reports_enabled' => true]);
    WebsiteHealthReport::factory()->for($health)->create();
    $business = Website::factory()->create(['weekly_ranking_reports_enabled' => true]);
    BusinessProfileConnection::factory()->for($business)->create(['location_name' => 'locations/456']);
    $disabled = Website::factory()->create(['weekly_ranking_reports_enabled' => false]);
    WebsiteHealthReport::factory()->for($disabled)->create();
    $this->artisan('ranking-reports:dispatch')->assertSuccessful();
    Queue::assertPushed(SendWeeklyRankingReport::class, 2);
});

test('viewer email links select the correct website and reports survive repeat delivery attempts', function (): void {
    Mail::fake();
    $website = Website::factory()->create();
    $other = Website::factory()->for($website->owner, 'owner')->create();
    $website->owner->update(['current_website_id' => $other->id]);
    $job = new SendWeeklyRankingReport($website);
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    $report = WeeklyReport::sole();
    $promptCount = 0;
    WeeklyOverviewWriter::fake(function () use (&$promptCount): string {
        $promptCount++;

        return 'Unexpected regeneration.';
    });
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    expect(WeeklyReport::count())->toBe(1)->and($promptCount)->toBe(0);
    $this->actingAs($website->owner)->get(route('admin.weekly-overviews.show', [$website, 'weekly_report' => $report->id]))->assertSuccessful()->assertSeeInOrder(preg_split('/\n\s*\n/', $report->overview));
    expect($website->owner->fresh()->current_website_id)->toBe($website->id);
});

test('populated overview renders stored movements findings work and next priority before email details', function (): void {
    $website = Website::factory()->create();
    $target = SeoTargetKeyword::factory()->for($website)->create(['term' => 'boiler repair', 'created_at' => now()->subMonth()]);
    foreach (['2026-09-03' => 19, '2026-09-10' => 9] as $date => $position) {
        SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $website->id, 'position' => $position, 'observed_at' => $date]);
    }
    WebsiteHealthReport::factory()->for($website)->create(['completed_at' => '2026-09-10', 'checks' => [['key' => 'links', 'label' => 'Broken links', 'status' => 'failed', 'message' => 'A service page contains a broken link.']]]);
    Optimisation::factory()->for($website)->create(['deployed_at' => '2026-09-09', 'status' => 'deployed', 'type' => 'internal_link', 'url' => 'https://example.com/services']);
    SearchConsoleConnection::factory()->for($website)->create();
    $this->mock(SearchConsoleClient::class, function ($mock): void {
        $mock->shouldReceive('weeklyPerformance')->once()->andReturn(['totals' => ['clicks' => 8, 'impressions' => 200, 'ctr' => 0.04, 'position' => 9], 'queries' => [], 'pages' => [['key' => 'https://example.com/services', 'clicks' => 8, 'impressions' => 200, 'ctr' => 0.04, 'position' => 9]]]);
        $mock->shouldReceive('weeklyPerformance')->once()->andReturn(['totals' => ['clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 12], 'queries' => [], 'pages' => [['key' => 'https://example.com/services', 'clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 12]]]);
    });
    BusinessProfileConnection::factory()->for($website)->create(['location_name' => 'locations/456']);
    $this->mock(BusinessProfileClient::class, function ($mock): void {
        $mock->shouldReceive('weeklyPerformance')->once()->andReturn(['multiDailyMetricTimeSeries' => []]);
        $mock->shouldReceive('reviewSummary')->once()->andReturn(['count' => 10, 'rating' => 5.0]);
    });
    $report = app(WeeklyReportGenerator::class)->generate($website, today());
    $this->actingAs($website->owner)->get(route('admin.dashboard'))->assertSuccessful()
        ->assertSee('Entered top 10')->assertSee('A service page contains a broken link.')
        ->assertSee('Internal link updated')->assertSee('Pages gaining visibility')->assertSee('Google reviews')
        ->assertSee('Resolve Broken links');
    $mail = new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website), $report);
    $html = $mail->render();
    expect(strpos($html, 'Your week with Sitewell'))->toBeLessThan(strpos($html, 'Detailed reporting'))
        ->and($html)->not->toContain('href="https://github.com');
    foreach (preg_split('/\n\s*\n/', $report->overview) as $paragraph) {
        $mail->assertSeeInHtml($paragraph);
    }
});

test('an unfinished saved snapshot resumes without fetching or recalculating its evidence', function (): void {
    $website = Website::factory()->create();
    $report = WeeklyReport::factory()->for($website)->create(['generated_at' => null, 'overview' => null]);
    $this->mock(WeeklyReportBuilder::class, fn ($mock) => $mock->shouldNotReceive('build'));
    $finished = app(WeeklyReportGenerator::class)->generate($website, today());
    expect($finished->id)->toBe($report->id)->and($finished->snapshot)->toBe($report->snapshot)->and($finished->generated_at)->not->toBeNull();
});

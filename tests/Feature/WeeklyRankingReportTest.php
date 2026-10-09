<?php

use App\Ai\Agents\WeeklyOverviewWriter;
use App\Jobs\SendWeeklyRankingReport;
use App\Mail\WeeklyRankingReport;
use App\Models\GoogleAdsConnection;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Services\RankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use App\Support\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    WeeklyOverviewWriter::fake(['Your saved weekly overview.']);
    Http::preventStrayRequests();
});

test('weekly ranking report compares stored search and seo performance', function () {
    $this->travelTo(Carbon::parse('2026-09-14'));
    $website = Website::factory()->create();
    $connection = SearchConsoleConnection::factory()->for($website)->create(['property_url' => 'sc-domain:example.com']);
    SeoSnapshot::factory()->for($website)->create([
        'snapshot_date' => '2026-07-31', 'completed_at' => '2026-07-31 12:00:00',
        'estimated_organic_traffic' => 800, 'organic_keywords' => 100, 'top_10_keywords' => 20,
    ]);
    $latest = SeoSnapshot::factory()->for($website)->create([
        'snapshot_date' => '2026-08-14', 'completed_at' => '2026-08-14 12:00:00',
        'estimated_organic_traffic' => 950, 'organic_keywords' => 115, 'top_10_keywords' => 24,
    ]);
    foreach ([
        ['month' => '2026-07-01', 'clicks' => 100, 'impressions' => 2000, 'position' => 9],
        ['month' => '2026-08-01', 'clicks' => 130, 'impressions' => 2400, 'position' => 7],
    ] as $metrics) {
        SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
            ...$metrics,
            'property_url' => $connection->property_url,
            'property_hash' => hash('sha256', $connection->property_url),
            'dimension_key' => SearchConsoleMetric::SITE_DIMENSION_KEY,
        ]);
    }
    SeoOpportunity::factory()->for($website)->for($latest, 'snapshot')->create(['title' => 'Improve services page', 'priority_score' => 90]);

    $report = app(RankingReportBuilder::class)->build($website);

    expect($report['highlights']->pluck('label')->all())->toContain('Estimated organic traffic', 'Ranking keywords', 'Google clicks', 'Average position')
        ->and($report['opportunities']->first()->title)->toBe('Improve services page');
    (new WeeklyRankingReport($website, $report))
        ->assertSeeInHtml('Google Search performance')
        ->assertSeeInHtml('Estimated rankings')
        ->assertSeeInHtml('Improve services page');
});

test('weekly ranking reports are queued to administrators, the owner, and website members', function () {
    Mail::fake();
    User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@example.com']);
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['email' => 'manager@example.com']);
    $viewer = User::factory()->create(['email' => 'viewer@example.com']);
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    (new SendWeeklyRankingReport($website))->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    Mail::assertQueued(WeeklyRankingReport::class, 4);
    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->hasTo('owner@example.com'));
    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->hasTo('manager@example.com'));
    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->hasTo('viewer@example.com'));
});

test('weekly email includes combined enabled Google Ads results for its reporting week', function (): void {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    config(['services.google_ads.api_url' => 'https://googleads.test/v25']);
    Http::fake(function (ClientRequest $request) {
        expect($request['query'])->toContain("campaign.status = 'ENABLED'")
            ->toContain("segments.date BETWEEN '2026-09-07' AND '2026-09-13'");

        return Http::response([['results' => [
            ['campaign' => ['id' => '1'], 'metrics' => ['impressions' => '1000', 'clicks' => '25', 'costMicros' => '15000000', 'conversions' => 2]],
            ['campaign' => ['id' => '2'], 'metrics' => ['impressions' => '500', 'clicks' => '10', 'costMicros' => '5000000', 'conversions' => 1]],
        ]]]);
    });

    (new SendWeeklyRankingReport($website))->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->adsSummary['campaigns'] === 2
        && $mail->adsSummary['impressions'] === 1500
        && $mail->adsSummary['clicks'] === 35
        && $mail->adsSummary['cost_micros'] === 20000000
        && $mail->adsSummary['conversions'] === 3.0);
});

test('weekly email omits Google Ads when no enabled campaigns return data', function (): void {
    Mail::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::COMPLETE, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    config(['services.google_ads.api_url' => 'https://googleads.test/v25']);
    Http::fake(['https://googleads.test/v25/*' => Http::response([['results' => []]])]);

    (new SendWeeklyRankingReport($website))->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->adsSummary === null);
    (new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website)))->assertDontSeeInHtml('Google Ads');
});

test('Ads access loss or an Ads API failure does not stop the weekly email', function (): void {
    Mail::fake();
    $owner = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH, 'membership_status' => 'active']);
    $website = Website::factory()->for($owner, 'owner')->create();
    GoogleAdsConnection::factory()->for($website)->create(['customer_id' => '1234567890', 'currency_code' => 'GBP']);
    config(['services.google_ads.api_url' => 'https://googleads.test/v25']);
    Http::fake(['https://googleads.test/v25/*' => Http::response(['error' => 'Unavailable'], 503)]);

    (new SendWeeklyRankingReport($website))->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    Http::assertNothingSent();

    $owner->update(['membership_tier' => MembershipPlan::COMPLETE]);
    (new SendWeeklyRankingReport($website))->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    Http::assertSentCount(1);
    Mail::assertQueued(WeeklyRankingReport::class, 2);
    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->adsSummary === null);
});

test('weekly ranking email shows active target states and links to target keywords', function (): void {
    $website = Website::factory()->create();
    $ranked = SeoTargetKeyword::factory()->for($website)->create(['term' => 'boiler repair barnsley']);
    SeoTargetKeywordRanking::factory()->for($ranked, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 20, 'observed_at' => now()->subWeek()]);
    SeoTargetKeywordRanking::factory()->for($ranked, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 12, 'observed_at' => now()]);
    $notFound = SeoTargetKeyword::factory()->for($website)->create(['term' => 'emergency plumber barnsley']);
    SeoTargetKeywordRanking::factory()->for($notFound, 'targetKeyword')->create(['website_id' => $website->id, 'status' => SeoTargetKeywordRanking::STATUS_NOT_FOUND, 'position' => null]);
    SeoTargetKeyword::factory()->for($website)->create(['term' => 'new target awaiting check']);
    $failed = SeoTargetKeyword::factory()->for($website)->create(['term' => 'target with failed check']);
    SeoTargetKeywordRanking::factory()->for($failed, 'targetKeyword')->create(['website_id' => $website->id, 'position' => 30, 'observed_at' => now()->subWeek()]);
    SeoTargetKeywordRanking::factory()->for($failed, 'targetKeyword')->create(['website_id' => $website->id, 'status' => SeoTargetKeywordRanking::STATUS_FAILED, 'position' => null, 'observed_at' => now()]);
    SeoTargetKeyword::factory()->for($website)->create(['term' => 'archived target', 'archived_at' => now()]);

    $mail = new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website));

    $mail->assertSeeInHtml('boiler repair barnsley')
        ->assertSeeInHtml('Position 12')
        ->assertSeeInHtml('emergency plumber barnsley')
        ->assertSeeInHtml('Not found in the top 100')
        ->assertSeeInHtml('new target awaiting check')
        ->assertSeeInHtml('target with failed check')
        ->assertSeeInHtml('latest check failed; previous result retained')
        ->assertSeeInHtml('Desktop ranking checks for the selected market')
        ->assertDontSeeInHtml('DataForSEO')
        ->assertSeeInHtml('View target keywords')
        ->assertDontSeeInHtml('archived target');
});

test('admins control weekly ranking email delivery independently', function (): void {
    $website = Website::factory()->create(['health_reports_enabled' => false, 'weekly_ranking_reports_enabled' => false]);
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->get(route('admin.websites.show', [$website, 'tab' => 'seo']))
        ->assertSuccessful()
        ->assertSee('Weekly ranking email')
        ->assertSee('Email delivery does not run paid checks.');
    $this->put(route('admin.weekly-ranking-report-settings.update', $website), ['weekly_ranking_reports_enabled' => true])
        ->assertRedirect()
        ->assertSessionHas('status', 'Weekly ranking emails enabled.');
    expect($website->fresh()->weekly_ranking_reports_enabled)->toBeTrue()
        ->and($website->fresh()->health_reports_enabled)->toBeFalse();

    $this->actingAs($viewer)->put(route('admin.weekly-ranking-report-settings.update', $website), ['weekly_ranking_reports_enabled' => false])->assertForbidden();
    $otherWebsite = Website::factory()->create();
    $this->actingAs($otherWebsite->owner)->put(route('admin.weekly-ranking-report-settings.update', $website), ['weekly_ranking_reports_enabled' => false])->assertForbidden();
    $website->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($website->owner)->put(route('admin.weekly-ranking-report-settings.update', $website), ['weekly_ranking_reports_enabled' => false])->assertForbidden();
});

test('weekly ranking dispatcher includes subscribed active websites with ranking data', function () {
    Queue::fake();
    $website = Website::factory()->create(['weekly_ranking_reports_enabled' => true, 'is_active' => true]);
    SeoSnapshot::factory()->for($website)->create();
    $disabled = Website::factory()->create(['weekly_ranking_reports_enabled' => false, 'health_reports_enabled' => true, 'is_active' => true]);
    SeoSnapshot::factory()->for($disabled)->create();
    $inactive = Website::factory()->create(['weekly_ranking_reports_enabled' => true, 'is_active' => false]);
    SeoSnapshot::factory()->for($inactive)->create();
    Website::factory()->create(['weekly_ranking_reports_enabled' => true, 'is_active' => true]);

    $this->artisan('ranking-reports:dispatch')->assertSuccessful();

    Queue::assertPushed(SendWeeklyRankingReport::class, 1);
});

test('weekly ranking report preference migration preserves existing delivery', function (): void {
    $enabled = Website::factory()->create(['health_reports_enabled' => true]);
    $disabled = Website::factory()->create(['health_reports_enabled' => false]);
    $migration = require database_path('migrations/2026_09_09_194418_add_weekly_ranking_reports_enabled_to_websites_table.php');

    $migration->down();

    try {
        $migration->up();

        expect($enabled->fresh()->weekly_ranking_reports_enabled)->toBeTrue()
            ->and($disabled->fresh()->weekly_ranking_reports_enabled)->toBeFalse()
            ->and(Website::factory()->create()->weekly_ranking_reports_enabled)->toBeFalse();
    } finally {
        if (! Schema::hasColumn('websites', 'weekly_ranking_reports_enabled')) {
            $migration->up();
        }
    }
});

test('weekly email waits for current market results then sends when they arrive', function () {
    Mail::fake();
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => true]);
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now()->subWeek(),
    ]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now(), 'device' => 'mobile',
    ]);
    $job = (new SendWeeklyRankingReport($website))->withFakeQueueInteractions();
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    $job->assertReleased(300);
    Mail::assertNothingQueued();
    Queue::assertNothingPushed();

    $this->travel(5)->minutes();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now(), 'position' => 7,
    ]);
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->report['targetKeywords']->sole()['latest']->position === 7
        && $mail->report['targetKeywords']->sole()['is_stale'] === false);
});

test('weekly email sends dated older results after its fixed waiting deadline', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => true]);
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now()->subWeek(),
    ]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now(), 'status' => SeoTargetKeywordRanking::STATUS_FAILED, 'position' => null,
    ]);
    $job = unserialize(serialize(new SendWeeklyRankingReport($website)))->withFakeQueueInteractions();
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    $job->assertReleased(300);
    Mail::assertNothingQueued();

    $this->travel(1)->hours();
    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));
    Mail::assertQueued(WeeklyRankingReport::class, fn (WeeklyRankingReport $mail): bool => $mail->report['targetKeywords']->sole()['is_stale'] === true);
});

test('weekly email identifies the observation date and missing fresh results', function () {
    $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));
    $website = Website::factory()->create();
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now()->subWeek(),
    ]);

    (new WeeklyRankingReport($website, app(RankingReportBuilder::class)->build($website)))
        ->assertSeeInHtml('Last checked: 7 Sep 2026 08:00')
        ->assertSeeInHtml('No fresh result for this week');
});

test('weekly email does not wait when automatic checks are disabled or targets are archived', function (bool $enabled) {
    Mail::fake();
    Queue::fake();
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => $enabled]);
    SeoTargetKeyword::factory()->for($website)->create(['archived_at' => $enabled ? now() : null]);
    $job = (new SendWeeklyRankingReport($website))->withFakeQueueInteractions();

    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    $job->assertNotReleased();
    Mail::assertQueued(WeeklyRankingReport::class);
    Queue::assertNothingPushed();
})->with([false, true]);

test('a current not found result is fresh enough for the weekly email', function () {
    Mail::fake();
    $website = Website::factory()->create(['seo_weekly_snapshots_enabled' => true]);
    $target = SeoTargetKeyword::factory()->for($website)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create([
        'website_id' => $website->id, 'observed_at' => now(), 'status' => SeoTargetKeywordRanking::STATUS_NOT_FOUND, 'position' => null,
    ]);
    $job = (new SendWeeklyRankingReport($website))->withFakeQueueInteractions();

    $job->handle(app(RankingReportBuilder::class), app(WebsiteMailRecipients::class));

    $job->assertNotReleased();
    Mail::assertQueued(WeeklyRankingReport::class);
});

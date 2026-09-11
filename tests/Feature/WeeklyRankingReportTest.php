<?php

use App\Jobs\SendWeeklyRankingReport;
use App\Mail\WeeklyRankingReport;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

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
        ->assertSeeInHtml('Exact DataForSEO desktop positions')
        ->assertSeeInHtml('View target keywords')
        ->assertDontSeeInHtml('archived target');
});

test('managers control weekly ranking email delivery independently', function (): void {
    $website = Website::factory()->create(['health_reports_enabled' => false, 'weekly_ranking_reports_enabled' => false]);
    $viewer = User::factory()->create();
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);

    $this->actingAs($website->owner)
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
    $this->actingAs($website->owner)->put(route('admin.weekly-ranking-report-settings.update', $website), ['weekly_ranking_reports_enabled' => false])->assertRedirect(route('admin.billing.index'));
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

<?php

use App\Jobs\SendWeeklyRankingReport;
use App\Mail\WeeklyRankingReport;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use App\Models\User;
use App\Models\Website;
use App\Services\RankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

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

test('weekly ranking dispatcher includes subscribed active websites with ranking data', function () {
    Queue::fake();
    $website = Website::factory()->create(['health_reports_enabled' => true, 'is_active' => true]);
    SeoSnapshot::factory()->for($website)->create();
    Website::factory()->create(['health_reports_enabled' => false, 'is_active' => true]);

    $this->artisan('ranking-reports:dispatch')->assertSuccessful();

    Queue::assertPushed(SendWeeklyRankingReport::class, 1);
});

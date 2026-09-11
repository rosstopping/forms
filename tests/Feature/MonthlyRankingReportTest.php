<?php

use App\Jobs\SendMonthlyRankingReport;
use App\Mail\MonthlyRankingReport;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Services\MonthlyRankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('monthly report compares complete months and surfaces query movement', function () {
    $this->travelTo(Carbon::parse('2026-09-08'));
    $website = Website::factory()->create();
    $connection = SearchConsoleConnection::factory()->for($website)->create(['property_url' => 'sc-domain:example.com']);
    $property = ['property_url' => $connection->property_url, 'property_hash' => hash('sha256', $connection->property_url)];
    foreach ([['month' => '2026-07-01', 'clicks' => 100, 'impressions' => 2000, 'position' => 12], ['month' => '2026-08-01', 'clicks' => 140, 'impressions' => 2500, 'position' => 8]] as $metrics) {
        SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([...$property, ...$metrics]);
    }
    foreach ([['month' => '2026-07-01', 'clicks' => 10, 'position' => 15], ['month' => '2026-08-01', 'clicks' => 24, 'position' => 7]] as $metrics) {
        SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([...$property, ...$metrics, 'dimension_key' => hash('sha256', 'query:website management'), 'query' => 'website management']);
    }
    $report = app(MonthlyRankingReportBuilder::class)->build($website->fresh());
    expect($report['latestSearch']->month->toDateString())->toBe('2026-08-01')
        ->and($report['highlights']->pluck('label')->all())->toContain('Google clicks', 'Average Google position')
        ->and($report['queryWins']->first()['query'])->toBe('website management');
    (new MonthlyRankingReport($website, $report))->assertSeeInHtml('August 2026')
        ->assertSeeInHtml('Searches moving in the right direction')->assertSeeInHtml('website management');
});

test('monthly reports exclude viewer members without changing weekly recipients', function () {
    Mail::fake();
    User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@example.com']);
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $website = Website::factory()->for($owner, 'owner')->create();
    $manager = User::factory()->create(['email' => 'manager@example.com']);
    $viewer = User::factory()->create(['email' => 'viewer@example.com']);
    $website->members()->attach($manager, ['role' => Website::MEMBER_ROLE_MANAGER]);
    $website->members()->attach($viewer, ['role' => Website::MEMBER_ROLE_VIEWER]);
    (new SendMonthlyRankingReport($website))->handle(app(MonthlyRankingReportBuilder::class), app(WebsiteMailRecipients::class));
    Mail::assertQueued(MonthlyRankingReport::class, 3);
    Mail::assertNotQueued(MonthlyRankingReport::class, fn (MonthlyRankingReport $mail): bool => $mail->hasTo('viewer@example.com'));
});

test('monthly dispatcher includes active subscribed websites with ranking data', function () {
    Queue::fake();
    $website = Website::factory()->create(['health_reports_enabled' => true, 'is_active' => true]);
    SearchConsoleConnection::factory()->for($website)->create();
    Website::factory()->create(['health_reports_enabled' => false, 'is_active' => true]);
    $this->artisan('ranking-reports:dispatch-monthly')->assertSuccessful();
    Queue::assertPushed(SendMonthlyRankingReport::class, 1);
});

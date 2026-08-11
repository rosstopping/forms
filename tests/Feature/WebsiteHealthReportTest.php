<?php

use App\Jobs\GenerateWebsiteHealthReport;
use App\Mail\WebsiteHealthReportReady;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteHealthAuditor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function websiteWithDomain(array $attributes = [], string $domain = 'example.com'): Website
{
    $website = Website::factory()->create($attributes);
    $website->domains()->create(['domain' => $domain, 'is_primary' => true]);

    return $website;
}

/** @return array<string, mixed> */
function searchConsoleReportData(): array
{
    return [
        'period' => ['start' => '2026-07-13', 'end' => '2026-08-10'],
        'totals' => ['clicks' => 125.0, 'impressions' => 2500.0, 'ctr' => 0.05, 'position' => 6.4],
        'queries' => [['query' => 'example services', 'clicks' => 40.0, 'impressions' => 500.0, 'ctr' => 0.08, 'position' => 3.2]],
        'pages' => [['page' => 'https://example.com/services', 'clicks' => 40.0, 'impressions' => 500.0, 'ctr' => 0.08, 'position' => 3.2]],
    ];
}

it('shows Search Console reporting on the website dashboard', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = websiteWithDomain();
    SearchConsoleConnection::factory()->for($website)->create(['connected_by' => $admin->id]);

    $this->mock(SearchConsoleClient::class)
        ->shouldReceive('report')
        ->once()
        ->andReturn(searchConsoleReportData());

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Search performance')
        ->assertSee('2,500')
        ->assertSee('5.0%')
        ->assertSee('example services')
        ->assertSee('example.com/services');
});

it('lets an administrator enable reports and queue one immediately', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = websiteWithDomain();

    $this->actingAs($admin)
        ->put(route('admin.websites.update', $website), [
            'user_id' => $website->user_id,
            'health_reports_enabled' => true,
        ])
        ->assertRedirect(route('admin.websites.show', $website));

    expect($website->fresh()->health_reports_enabled)->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.website-health-reports.store', $website))
        ->assertRedirect();

    $report = $website->healthReports()->firstOrFail();
    expect($report->status)->toBe(WebsiteHealthReport::STATUS_PENDING);
    Queue::assertPushed(GenerateWebsiteHealthReport::class, fn ($job) => $job->report->is($report));
});

it('allows an owner to view only reports for their website', function (): void {
    $owner = User::factory()->create();
    $website = websiteWithDomain(['user_id' => $owner->id]);
    $otherWebsite = websiteWithDomain([], 'other.example.com');
    $report = WebsiteHealthReport::factory()->for($website)->create();
    WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/about',
        'url_hash' => hash('sha256', 'https://example.com/about'),
        'title' => 'About us',
        'checks' => [['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'No meta description was found.']],
    ]);
    $otherReport = WebsiteHealthReport::factory()->for($otherWebsite)->create();

    $this->actingAs($owner)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertSee('Website health report')
        ->assertSee('Page-by-page analysis')
        ->assertSee('About us')
        ->assertDontSee('AI remediation prompt');

    $this->actingAs($owner)
        ->get(route('admin.website-health-reports.show', [$otherWebsite, $otherReport]))
        ->assertForbidden();
});

it('shows administrators a copyable AI prompt containing every report issue', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = websiteWithDomain();
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'checks' => [
            ['category' => 'security', 'key' => 'content_security_policy', 'label' => 'Content Security Policy', 'status' => 'warning', 'message' => 'The security header is missing.', 'details' => []],
            ['category' => 'site_wide_seo', 'key' => 'missing_titles', 'label' => 'Missing titles', 'status' => 'failed', 'message' => 'One page has no title.', 'details' => []],
            ['category' => 'availability', 'key' => 'https', 'label' => 'HTTPS enabled', 'status' => 'passed', 'message' => 'HTTPS is enabled.', 'details' => []],
        ],
    ]);
    WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'url_hash' => hash('sha256', 'https://example.com/services'),
        'checks' => [
            ['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'No meta description was found.'],
            ['key' => 'h1', 'label' => 'Primary heading', 'status' => 'passed', 'message' => 'The page has one H1.'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertSee('AI remediation prompt')
        ->assertSee('Copy prompt')
        ->assertSee('[FAILED] Missing titles: One page has no title.')
        ->assertSee('[WARNING] Content Security Policy: The security header is missing.')
        ->assertSee('https://example.com/services')
        ->assertSee('[WARNING] Meta description: No meta description was found.')
        ->assertDontSee('[PASSED] HTTPS enabled')
        ->assertDontSee('[PASSED] Primary heading');
});

it('stores page titles longer than the varchar limit', function (): void {
    $report = WebsiteHealthReport::factory()->create();
    $longRedirectTitle = 'Redirecting to https://example.com/search?months='.str_repeat('September%202026%2C', 30);

    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'title' => $longRedirectTitle,
    ]);

    expect($page->fresh()->title)->toBe($longRedirectTitle)
        ->and(mb_strlen($page->title))->toBeGreaterThan(255);
});

it('audits a website and queues the completed report for admins and the owner', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    $website = websiteWithDomain(['user_id' => $owner->id]);
    SearchConsoleConnection::factory()->for($website)->create(['connected_by' => $admin->id]);
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'status' => WebsiteHealthReport::STATUS_PENDING,
        'completed_at' => null,
    ]);

    Http::fake([
        'https://example.com' => Http::response('<html lang="en"><head><title>Example</title><meta name="description" content="A useful description"><meta name="viewport" content="width=device-width"><link rel="canonical" href="https://example.com"></head><body><h1>Welcome</h1><img src="logo.png" alt="Logo"></body></html>', 200, [
            'Strict-Transport-Security' => 'max-age=31536000',
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]),
        'https://example.com/' => Http::response('<html lang="en"><head><title>Example</title><meta name="description" content="A useful description"><meta name="viewport" content="width=device-width"><link rel="canonical" href="https://example.com"></head><body><h1>Welcome</h1><img src="logo.png" alt="Logo"></body></html>', 200, [
            'Strict-Transport-Security' => 'max-age=31536000',
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]),
        'https://example.com/robots.txt' => Http::response('User-agent: *', 200),
        'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>', 200),
    ]);

    $searchConsole = $this->mock(SearchConsoleClient::class);
    $searchConsole->shouldReceive('report')->once()->andReturn(searchConsoleReportData());

    (new GenerateWebsiteHealthReport($report))->handle(app(WebsiteHealthAuditor::class), $searchConsole);

    $report->refresh();
    expect($report->status)->toBe(WebsiteHealthReport::STATUS_COMPLETED)
        ->and($report->passed_checks)->toBeGreaterThan(0)
        ->and($report->pages)->toHaveCount(1)
        ->and($report->metrics['pages_analyzed'])->toBe(1)
        ->and($report->metrics['search_console']['totals']['clicks'])->toBe(125)
        ->and($report->completed_at)->not->toBeNull();

    Mail::assertQueued(WebsiteHealthReportReady::class, 2);
    Mail::assertQueued(WebsiteHealthReportReady::class, fn ($mail) => $mail->hasTo($admin->email));
    Mail::assertQueued(WebsiteHealthReportReady::class, fn ($mail) => $mail->hasTo($owner->email));

    (new WebsiteHealthReportReady($report->fresh(['website'])))
        ->assertSeeInHtml('Weekly website health report')
        ->assertSeeInHtml('Google Search Console')
        ->assertSeeInHtml('example services')
        ->assertSeeInHtml('View the full report');
});

it('dispatches only due enabled websites from the scheduler command', function (): void {
    Queue::fake();
    $due = websiteWithDomain(['health_reports_enabled' => true]);
    $disabled = websiteWithDomain(['health_reports_enabled' => false], 'disabled.example.com');

    $this->artisan('health-reports:dispatch')->assertSuccessful();

    expect($due->healthReports()->count())->toBe(1)
        ->and($disabled->healthReports()->count())->toBe(0);
    Queue::assertPushed(GenerateWebsiteHealthReport::class, 1);
});

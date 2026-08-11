<?php

use App\Jobs\GenerateWebsiteHealthReport;
use App\Mail\WebsiteHealthReportReady;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\Form;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\PageSpeedInsightsClient;
use App\Services\SearchConsoleClient;
use App\Services\WebsiteHealthAuditor;
use App\Services\WebsiteHealthReportPromptGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

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
        ->assertSee('example.com/services')
        ->assertSee('Position')
        ->assertSee('3.2')
        ->assertSee('View all data')
        ->assertSee(route('admin.search-console.performance', $website));
});

it('shows sortable Search Console queries and landing pages to website users', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $website = websiteWithDomain(['user_id' => $owner->id]);
    $connection = SearchConsoleConnection::factory()->for($website)->create([
        'connected_by' => $owner->id,
        'property_url' => 'https://example.com/',
    ]);

    $this->mock(SearchConsoleClient::class)
        ->shouldReceive('queryPagePerformance')
        ->once()
        ->withArgs(fn (SearchConsoleConnection $requestedConnection, int $limit): bool => $requestedConnection->is($connection) && $limit === 25000)
        ->andReturn([
            ['query' => 'lower ranking query', 'page' => 'https://example.com/lower-ranking-page', 'clicks' => 18.0, 'impressions' => 240.0, 'ctr' => 0.075, 'position' => 12.4],
            ['query' => 'higher ranking query', 'page' => 'https://example.com/higher-ranking-page', 'clicks' => 12.0, 'impressions' => 200.0, 'ctr' => 0.06, 'position' => 3.1],
        ])
        ->shouldReceive('pagePerformance')
        ->once()
        ->withArgs(fn (SearchConsoleConnection $requestedConnection, int $limit): bool => $requestedConnection->is($connection) && $limit === 25000)
        ->andReturn([
            ['page' => 'https://example.com/strong-page', 'clicks' => 20.0, 'impressions' => 300.0, 'ctr' => 0.067, 'position' => 2.5],
            ['page' => 'https://example.com/weak-page', 'clicks' => 8.0, 'impressions' => 150.0, 'ctr' => 0.053, 'position' => 14.2],
        ]);

    $url = route('admin.search-console.performance', [
        $website,
        'query_sort' => 'position',
        'query_direction' => 'asc',
        'page_sort' => 'position',
        'page_direction' => 'desc',
    ]);

    $this->actingAs($owner)
        ->get($url)
        ->assertSuccessful()
        ->assertSee('All available queries')
        ->assertSee('All available landing pages')
        ->assertSee('Average position')
        ->assertSee('Ranking page')
        ->assertSeeInOrder(['higher ranking query', 'lower ranking query'])
        ->assertSeeInOrder(['example.com/weak-page', 'example.com/strong-page'])
        ->assertSee('query_sort=clicks')
        ->assertSee('page_sort=clicks');

    $this->actingAs($otherUser)
        ->get($url)
        ->assertForbidden();
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

it('lets a website owner manually queue a health report', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $website = websiteWithDomain(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('admin.websites.show', $website))
        ->assertSuccessful()
        ->assertSee('Run report now');

    $this->actingAs($owner)
        ->post(route('admin.website-health-reports.store', $website))
        ->assertRedirect();

    $report = $website->healthReports()->sole();

    expect($report->status)->toBe(WebsiteHealthReport::STATUS_PENDING);
    Queue::assertPushed(GenerateWebsiteHealthReport::class, fn ($job) => $job->report->is($report));

    $this->actingAs($otherUser)
        ->post(route('admin.website-health-reports.store', $website))
        ->assertForbidden();
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

it('shows a friendly report through a signed link without authentication', function (): void {
    $website = websiteWithDomain();
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'metrics' => [
            'pages_analyzed' => 1,
            'search_console' => searchConsoleReportData(),
        ],
        'checks' => [
            ['category' => 'security', 'key' => 'content_security_policy', 'label' => 'Content Security Policy', 'status' => 'warning', 'message' => 'The security header is missing.', 'details' => []],
            ['category' => 'availability', 'key' => 'https', 'label' => 'HTTPS enabled', 'status' => 'passed', 'message' => 'HTTPS is enabled.', 'details' => []],
        ],
    ]);
    WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'url_hash' => hash('sha256', 'https://example.com/services'),
        'title' => str_repeat('Long services title ', 5),
        'meta_description' => str_repeat('Long services description ', 8),
        'checks' => [
            ['key' => 'page_title', 'label' => 'Page title', 'status' => 'warning', 'message' => 'Title: present'],
            ['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'Meta description is present.'],
            ['key' => 'h1', 'label' => 'Primary heading', 'status' => 'passed', 'message' => 'The page has one H1.'],
        ],
    ]);

    $signedUrl = URL::temporarySignedRoute('website-health-reports.show', now()->addDays(30), $report);

    $this->get($signedUrl)
        ->assertSuccessful()
        ->assertSee('Your website health report')
        ->assertSeeTextInOrder(['How people found the website', 'What needs attention'])
        ->assertSee('Top search terms')
        ->assertSee('example services')
        ->assertSee('Top landing pages')
        ->assertSee('/services')
        ->assertSee('500')
        ->assertSee('What needs attention')
        ->assertSee('Content Security Policy')
        ->assertSee('Aim for 65 or fewer')
        ->assertSee('Aim for 170 or fewer')
        ->assertDontSee('Meta description is present.')
        ->assertDontSee('HTTPS enabled')
        ->assertDontSee('AI remediation prompt')
        ->assertDontSee('Copilot');

    $this->get(route('website-health-reports.show', $report))->assertForbidden();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertSee('Aim for 65 or fewer')
        ->assertSee('Aim for 170 or fewer');

    expect(app(WebsiteHealthReportPromptGenerator::class)->generate($report->fresh(['website', 'pages'])))
        ->toContain('Aim for 65 or fewer')
        ->toContain('Aim for 170 or fewer')
        ->not->toContain('[WARNING] Meta description: Meta description is present.');
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

it('hides the manual AI prompt when a GitHub repository is connected', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = websiteWithDomain();
    WebsiteRepository::factory()->for($website)->create(['full_name' => 'acme/example-site']);
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'checks' => [
            ['category' => 'security', 'key' => 'content_security_policy', 'label' => 'Content Security Policy', 'status' => 'warning', 'message' => 'The security header is missing.', 'details' => []],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertDontSee('AI remediation prompt')
        ->assertDontSee('Copy prompt')
        ->assertSee('Prepare repository fix')
        ->assertSee('Start Copilot remediation');
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
    GithubUserAuthorization::factory()->for($admin)->create();
    $owner = User::factory()->create();
    $website = websiteWithDomain(['user_id' => $owner->id]);
    Form::factory()->for($website)->create();
    SearchConsoleConnection::factory()->for($website)->create(['connected_by' => $admin->id]);
    $installation = GithubInstallation::factory()->create(['installed_by' => $admin->id]);
    $repository = WebsiteRepository::factory()->for($website)->create([
        'github_installation_id' => $installation->id,
        'full_name' => 'acme/example-site',
    ]);
    $contentPlan = ContentPlan::factory()->for($website)->create(['created_by' => $admin->id, 'enabled' => true]);
    $generation = ContentGeneration::factory()->for($contentPlan, 'plan')->for($repository, 'repository')->create([
        'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
        'requested_by' => $admin->id,
        'copilot_task_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        'pull_request_number' => 999999,
        'pull_request_url' => 'https://github.com/acme/example-site/pull/999999',
        'pull_request_state' => 'open',
        'merged_at' => null,
        'completed_at' => null,
    ]);
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
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestDetails')->once()->withArgs(fn ($passedRepository, int $number): bool => $passedRepository->is($repository) && $number === 999999)->andThrow(new RuntimeException('Pull request not found.'));
    $github->shouldReceive('pullRequestForHead')->once()->withArgs(fn ($passedRepository, string $headRef): bool => $passedRepository->is($repository) && $headRef === 'copilot/content-update')->andReturn([
        'number' => 42,
        'html_url' => 'https://github.com/acme/example-site/pull/42',
    ]);
    $github->shouldReceive('pullRequestDetails')->once()->withArgs(fn ($passedRepository, int $number): bool => $passedRepository->is($repository) && $number === 42)->andReturn([
        'pull_request' => [
            'title' => 'Publish a guide to choosing event forms',
            'body' => 'Added a practical guide based on this week’s search demand.',
            'html_url' => 'https://github.com/acme/example-site/pull/42',
            'merged_at' => now()->subDay()->toIso8601String(),
            'additions' => 180,
            'deletions' => 12,
            'changed_files' => 2,
        ],
        'files' => [
            ['filename' => 'resources/views/guides/event-forms.blade.php', 'status' => 'added', 'additions' => 170, 'deletions' => 0],
            ['filename' => 'routes/web.php', 'status' => 'modified', 'additions' => 10, 'deletions' => 12],
        ],
    ]);

    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn([
        'sessions' => [['head_ref' => 'copilot/content-update']],
    ]);
    $pageSpeed = $this->mock(PageSpeedInsightsClient::class);
    $pageSpeed->shouldReceive('audit')->once()->andReturn([
        'pages' => [['url' => 'https://example.com/', 'strategy' => 'mobile', 'available' => false]],
        'checks' => [],
    ]);

    (new GenerateWebsiteHealthReport($report))->handle(app(WebsiteHealthAuditor::class), $searchConsole, $github, $copilot, $pageSpeed);

    $report->refresh();
    expect($report->status)->toBe(WebsiteHealthReport::STATUS_COMPLETED)
        ->and($report->passed_checks)->toBeGreaterThan(0)
        ->and($report->pages)->toHaveCount(1)
        ->and($report->metrics['pages_analyzed'])->toBe(1)
        ->and($report->metrics['forms_count'])->toBe(1)
        ->and($report->metrics['pagespeed'][0]['strategy'])->toBe('mobile')
        ->and($report->metrics['search_console']['totals']['clicks'])->toBe(125)
        ->and($report->metrics['content_updates'][0]['title'])->toBe('Publish a guide to choosing event forms')
        ->and($report->metrics['content_updates'][0]['changed_files'])->toBe(2)
        ->and($report->completed_at)->not->toBeNull();

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_COMPLETED)
        ->and($generation->fresh()->pull_request_number)->toBe(42)
        ->and($generation->fresh()->pull_request_url)->toBe('https://github.com/acme/example-site/pull/42')
        ->and($generation->fresh()->pull_request_state)->toBe('closed')
        ->and($generation->fresh()->merged_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertSee('Structured data and schema recommendations')
        ->assertSee('Organization schema opportunity')
        ->assertSee('Last seven days of forms');

    Mail::assertQueued(WebsiteHealthReportReady::class, 2);
    Mail::assertQueued(WebsiteHealthReportReady::class, fn ($mail) => $mail->hasTo($admin->email));
    Mail::assertQueued(WebsiteHealthReportReady::class, fn ($mail) => $mail->hasTo($owner->email));

    (new WebsiteHealthReportReady($report->fresh(['website'])))
        ->assertSeeInHtml('Weekly website health report')
        ->assertSeeInHtml('Forms in the last seven days')
        ->assertSeeInHtml('Content updates this week')
        ->assertSeeInHtml('Publish a guide to choosing event forms')
        ->assertSeeInHtml('resources/views/guides/event-forms.blade.php')
        ->assertSeeInHtml('Google Search Console')
        ->assertSeeInHtml('example services')
        ->assertSeeInHtml('View the full report')
        ->assertSeeInHtml('does not require you to log in')
        ->assertSeeInHtml('signature=');

    $this->get(URL::temporarySignedRoute('website-health-reports.show', now()->addDays(30), $report))
        ->assertSuccessful()
        ->assertSee('Content changes this week')
        ->assertSee('Publish a guide to choosing event forms')
        ->assertSee('Added a practical guide based on this week’s search demand.')
        ->assertSee('Structured data and schema recommendations')
        ->assertSee('Organization schema opportunity')
        ->assertSee('resources/views/guides/event-forms.blade.php');
});

it('omits form submission details from audit emails for websites without forms', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = websiteWithDomain();
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'metrics' => [
            'forms_count' => 0,
            'legitimate_submissions' => 0,
            'spam_submissions' => 0,
            'email_failures' => 0,
            'webhook_failures' => 0,
        ],
    ]);

    (new WebsiteHealthReportReady($report->fresh(['website'])))
        ->assertDontSeeInHtml('Forms in the last seven days')
        ->assertDontSeeInHtml('legitimate submissions');

    $this->actingAs($admin)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertDontSee('Last seven days of forms');
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

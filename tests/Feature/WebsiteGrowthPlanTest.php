<?php

use App\Ai\Agents\WebsiteGrowthPlanner;
use App\Jobs\GenerateWebsiteGrowthPlan;
use App\Mail\WebsiteGrowthPlanReady;
use App\Models\ContentPlan;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\SeoKeyword;
use App\Models\SeoSnapshot;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGrowthPlan;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteSetup;
use App\Services\WebsiteGrowthPlanBuilder;
use App\Services\WebsiteGrowthPlanPresentation;
use App\Support\WebsiteNavigation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

function generatedGrowthPlanResponse(array $keywordIds = []): array
{
    return [
        'headline' => 'More of the right enquiries.',
        'priorities' => [
            ['title' => 'Fix the foundations', 'action' => 'Resolve confirmed indexing and availability issues before expanding the site.', 'outcome' => 'Make the key pages easier to find.'],
            ['title' => 'Strengthen the service pages', 'action' => 'Add verified installation examples and clearer service answers to the existing heating page.', 'outcome' => 'Help facilities managers choose the right service.'],
            ['title' => 'Make enquiries easier', 'action' => 'Connect relevant content to the enquiry pages with clear internal links.', 'outcome' => 'Turn relevant visits into qualified enquiries.'],
        ],
        'focus_keyword_ids' => array_slice($keywordIds, 0, 6),
        'summary' => 'Improve the existing heating pages to attract qualified installation enquiries.',
        'services' => 'Commercial heating', 'locations' => 'Sheffield', 'audience' => 'Facilities managers',
        'difference' => 'Confirm the business credentials before publishing claims.',
        'success_measure' => 'Qualified enquiries and relevant organic search clicks.',
        'dependencies' => 'Access to the website and approval of accurate service details.',
        'limitations' => 'Saved estimates are a baseline. Six-month ranges are illustrative, not guaranteed.',
        'roadmap' => array_fill(0, 6, 'Review the saved evidence and improve the relevant service pages.'),
        'keyword_actions' => array_map(fn (int $id): array => ['keyword_id' => $id,
            'action' => 'Improve the existing heating page with verified installation examples and clearer service answers.',
            'target_best' => 5, 'target_worst' => 15, 'target_reason' => 'Illustrative target assuming the agreed improvements are completed.'], $keywordIds),
        'suggested_keywords' => [],
    ];
}

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
    Http::preventStrayRequests();
    WebsiteGrowthPlanner::fake(fn (string $prompt): array => generatedGrowthPlanResponse(array_column(json_decode($prompt, true)['baseline_report']['keywords'], 'keyword_id')));
    $this->staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->client = User::factory()->create();
    $this->site = Website::factory()->for($this->client, 'owner')->create();
    $this->site->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    config(['services.dataforseo.location_code' => 2826, 'services.dataforseo.language_code' => 'en']);
});

test('opening the plan shows one generate button and no report-building form or provider calls', function (): void {
    $this->actingAs($this->staff)->get(route('admin.website-growth-plans.index', $this->site))
        ->assertSuccessful()->assertSee('Generate plan')->assertDontSee('Priority services')->assertDontSee('Approve and publish')
        ->assertDontSee('name="keywords', false)->assertDontSee('<textarea', false);
    expect(WebsiteGrowthPlan::count())->toBe(0);
    Queue::assertNothingPushed();
    WebsiteGrowthPlanner::assertNeverPrompted();
    Http::assertNothingSent();
});

test('a single button queues a plan with saved setup content pages and ranking context', function (): void {
    WebsiteSetup::factory()->for($this->site)->create(['data' => ['business' => ['services' => 'Commercial heating', 'locations' => 'Sheffield']]]);
    ContentPlan::factory()->for($this->site)->create(['audience' => 'Facilities managers', 'guidance' => 'Focus on commercial installations.']);
    $target = SeoTargetKeyword::factory()->for($this->site)->create(['term' => 'commercial heating sheffield', 'intended_url' => 'https://example.com/heating', 'assignment_role' => 'primary']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 23, 'ranking_url' => 'https://example.com/old-page']);
    $health = WebsiteHealthReport::factory()->for($this->site)->create(['failed_checks' => 1, 'checks' => [['label' => 'Missing sitemap', 'message' => 'Review sitemap discovery.', 'status' => 'failed']]]);
    $health->pages()->create(['url' => 'https://example.com/heating', 'url_hash' => hash('sha256', 'https://example.com/heating'), 'depth' => 0, 'title' => 'Commercial heating', 'meta_description' => 'Commercial installation services.']);
    $this->actingAs($this->staff)->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    $plan = WebsiteGrowthPlan::firstOrFail();
    expect($plan->status)->toBe('queued')->and($plan->published_at)->toBeNull()
        ->and($plan->brief['saved_business_brief']['services'])->toBe('Commercial heating')
        ->and($plan->brief['content_guidance']['guidance'])->toBe('Focus on commercial installations.')
        ->and($plan->brief['own_page_urls'])->toContain('https://example.com/heating')
        ->and($plan->brief['baseline_report']['keywords'][0]['baseline']['position'])->toBe(23);
    Queue::assertPushed(GenerateWebsiteGrowthPlan::class, 1);
    WebsiteGrowthPlanner::assertNeverPrompted();
    Mail::assertNothingQueued();
});

test('generation writes a complete ready protected report without a publish step', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->site)->create(['term' => 'commercial heating sheffield']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 23, 'ranking_url' => 'https://example.com/heating']);
    $this->actingAs($this->staff)->post(route('admin.website-growth-plans.store', $this->site));
    $plan = WebsiteGrowthPlan::firstOrFail();
    (new GenerateWebsiteGrowthPlan($plan))->handle(app(WebsiteGrowthPlanBuilder::class));
    $plan->refresh();
    expect($plan->isReady())->toBeTrue()->and($plan->completed_at)->not->toBeNull()
        ->and($plan->report['roadmap'])->toHaveCount(6)->and($plan->report['keywords'][0]['baseline']['position'])->toBe(23);
    $this->get($plan->shareUrl())->assertSuccessful()->assertSee('#23')->assertSee('#5–#15')->assertSee('Month 6')->assertSee('Improve the existing heating pages');
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertSuccessful()->assertSee('Open shareable report')->assertDontSee('<textarea', false)->assertDontSee('data-growth-plan-document', false);
    Mail::assertNothingQueued();
});

test('generation snapshots only relevant recent same-market desktop ranking evidence', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->site)->create(['term' => 'commercial heating sheffield']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 23, 'observed_at' => now()->subDays(2)]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 1, 'device' => 'mobile']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 2, 'location_code' => 2840]);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['status' => 'failed', 'position' => null]);
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    expect($context['baseline_report']['keywords'][0]['baseline']['position'])->toBe(23);
});

test('stale rankings are unavailable and an observed absence remains distinct', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->site)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['observed_at' => now()->subDays(31)]);
    $builder = app(WebsiteGrowthPlanBuilder::class);
    expect($builder->snapshot($this->site)['baseline_report']['keywords'][0]['baseline'])->toBeNull();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['status' => 'not_found', 'position' => null, 'ranking_url' => null]);
    expect($builder->snapshot($this->site)['baseline_report']['keywords'][0]['baseline']['status'])->toBe('not_found');
});

test('duplicate clicks reuse one pending generation and GET never regenerates', function (): void {
    $this->actingAs($this->staff);
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    $plan = WebsiteGrowthPlan::firstOrFail();
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertSuccessful()->assertHeader('Refresh', '5')->assertSee('Your plan is being prepared');
    expect(WebsiteGrowthPlan::count())->toBe(1);
    Queue::assertPushed(GenerateWebsiteGrowthPlan::class, 1);
    WebsiteGrowthPlanner::assertNeverPrompted();
});

test('a completed generation job is idempotent and regeneration preserves earlier reports', function (): void {
    $this->actingAs($this->staff)->post(route('admin.website-growth-plans.store', $this->site));
    $plan = WebsiteGrowthPlan::firstOrFail();
    $calls = 0;
    WebsiteGrowthPlanner::fake(function () use (&$calls): array {
        $calls++;

        return generatedGrowthPlanResponse();
    });
    $job = new GenerateWebsiteGrowthPlan($plan);
    $job->handle(app(WebsiteGrowthPlanBuilder::class));
    $snapshot = $plan->fresh()->report;
    $job->handle(app(WebsiteGrowthPlanBuilder::class));
    expect($calls)->toBe(1);
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    expect(WebsiteGrowthPlan::count())->toBe(2)->and($plan->fresh()->report)->toBe($snapshot);
    $this->get($plan->shareUrl())->assertSuccessful();
});

test('only ready signed reports are publicly readable and links expire', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['status' => 'queued']);
    $this->get($plan->shareUrl())->assertNotFound();
    $plan->update(['status' => 'completed', 'published_at' => now()]);
    $this->get(route('website-growth-plans.report', $plan))->assertForbidden();
    $this->get($plan->shareUrl())->assertSuccessful()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(URL::temporarySignedRoute('website-growth-plans.report', now()->subMinute(), $plan))->assertForbidden();
    $plan->update(['status' => 'failed']);
    $this->get($plan->shareUrl())->assertNotFound();
});

test('staff explicitly email a ready plan once to an attached customer', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create();
    $route = route('admin.website-growth-plans.send', [$this->site, $plan]);
    $this->actingAs($this->staff)->post($route, ['recipient' => $this->client->email])->assertSessionHasErrors('recipient');
    $plan->update(['published_at' => now()]);
    $this->post($route, ['recipient' => 'unrelated@example.com'])->assertSessionHasErrors('recipient');
    $this->post($route, ['recipient' => $this->client->email])->assertRedirect()->assertSessionHasNoErrors();
    $this->post($route, ['recipient' => $this->client->email])->assertRedirect();
    Mail::assertQueued(WebsiteGrowthPlanReady::class, fn ($mail): bool => $mail->hasTo($this->client->email) && str_contains($mail->reportUrl, 'signature='));
    Mail::assertQueuedCount(1);
});

test('plan emails render the private report link and client language', function (): void {
    $mail = new WebsiteGrowthPlanReady('Acme Heating', 'https://sitewell.test/private-plan');
    $mail->assertSeeInHtml('Acme Heating');
    $mail->assertSeeInHtml('https://sitewell.test/private-plan');
    $mail->assertSeeInHtml('not guaranteed results');
});

test('customers cannot access staff generation tools even for their own website', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create();
    $this->actingAs($this->client);
    $this->get(route('admin.website-growth-plans.index', $this->site))->assertForbidden();
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertForbidden();
    $this->get(route('admin.website-growth-plans.preview', [$this->site, $plan]))->assertForbidden();
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertForbidden();
    $this->post(route('admin.website-growth-plans.send', [$this->site, $plan]), ['recipient' => $this->client->email])->assertForbidden();
    Queue::assertNothingPushed();
});

test('assigned staff and nested plans remain website scoped', function (): void {
    $assigned = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create();
    $this->actingAs($assigned)->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertForbidden();
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertForbidden();
    $assigned->assignedWebsites()->attach($this->site);
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertSuccessful();
    $otherPlan = WebsiteGrowthPlan::factory()->create(['published_at' => now()]);
    $this->actingAs($this->staff)->get(route('admin.website-growth-plans.edit', [$this->site, $otherPlan]))->assertNotFound();
    $this->post(route('admin.website-growth-plans.send', [$this->site, $otherPlan]), ['recipient' => $this->client->email])->assertNotFound();
});

test('websites without a client can open the report page but cannot generate a plan', function (): void {
    $website = Website::factory()->for($this->staff, 'owner')->create();
    $this->actingAs($this->staff)->get(route('admin.website-growth-plans.index', $website))->assertSuccessful()->assertSee('Activate this website’s client service');
    $this->post(route('admin.website-growth-plans.store', $website))->assertUnprocessable();
    expect(WebsiteGrowthPlan::count())->toBe(0);
});

test('active managed clients can generate without an attached customer account', function (): void {
    $this->site->update(['user_id' => $this->staff->id, 'service_package' => 'growth', 'service_status' => 'active']);
    $this->actingAs($this->staff)->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    $plan = WebsiteGrowthPlan::firstOrFail();
    (new GenerateWebsiteGrowthPlan($plan))->handle(app(WebsiteGrowthPlanBuilder::class));
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertSuccessful()->assertSee('Open shareable report')->assertDontSee('Email plan');
    Mail::assertNothingQueued();
});

test('sparse context still generates a useful report without fabricated ranking targets', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->site)->create();
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    $report = app(WebsiteGrowthPlanBuilder::class)->generate($context);
    expect($report['keywords'][0]['baseline'])->toBeNull()->and($report['keywords'][0]['target_best'])->toBeNull()
        ->and($report['summary'])->not->toBeEmpty()->and($report['roadmap'])->toHaveCount(6);
});

test('generation failures show a retry path without exposing provider errors or earlier reports', function (): void {
    $previous = WebsiteGrowthPlan::factory()->for($this->site)->create(['published_at' => now()]);
    $this->actingAs($this->staff)->post(route('admin.website-growth-plans.store', $this->site));
    $plan = WebsiteGrowthPlan::latest('id')->firstOrFail();
    WebsiteGrowthPlanner::fake(function (): never {
        throw new RuntimeException('Sensitive provider diagnostic');
    });
    $job = new GenerateWebsiteGrowthPlan($plan);
    expect(fn () => $job->handle(app(WebsiteGrowthPlanBuilder::class)))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Sensitive provider diagnostic'));
    $this->get(route('admin.website-growth-plans.edit', [$this->site, $plan]))->assertSuccessful()
        ->assertSee('The plan could not be generated')->assertSee('Generate plan')->assertDontSee('Sensitive provider diagnostic');
    $this->get($previous->shareUrl())->assertSuccessful();
    $this->post(route('admin.website-growth-plans.store', $this->site))->assertRedirect();
    expect(WebsiteGrowthPlan::count())->toBe(3);
});

test('AI cannot substitute unknown keyword ids or change measured positions', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->site)->create();
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['position' => 23]);
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    WebsiteGrowthPlanner::fake([generatedGrowthPlanResponse([999])]);
    expect(fn () => app(WebsiteGrowthPlanBuilder::class)->generate($context))->toThrow(ValidationException::class);
    WebsiteGrowthPlanner::fake([[...generatedGrowthPlanResponse([0]), 'position' => 1]]);
    expect(app(WebsiteGrowthPlanBuilder::class)->generate($context)['keywords'][0]['baseline']['position'])->toBe(23);
});

test('suggested keywords have no invented baseline and only use saved own-page destinations', function (): void {
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    $context['own_page_urls'] = ['https://example.com/heating'];
    WebsiteGrowthPlanner::fake([[...generatedGrowthPlanResponse(), 'suggested_keywords' => [
        ['term' => 'commercial heating', 'intended_url' => 'https://example.com/heating', 'action' => 'Suggested focus for the existing service page.'],
        ['term' => 'unsupported destination', 'intended_url' => 'https://other.example/private', 'action' => 'Wrong page.'],
    ]]]);
    $keywords = app(WebsiteGrowthPlanBuilder::class)->generate($context)['keywords'];
    expect($keywords)->toHaveCount(1)->and($keywords[0]['suggested'])->toBeTrue()->and($keywords[0]['baseline'])->toBeNull()->and($keywords[0]['target_best'])->toBeNull();
    expect($this->site->seoTargetKeywords()->count())->toBe(0);
});

test('saved SEO discoveries are reused when no keywords were manually tracked', function (): void {
    $snapshot = SeoSnapshot::factory()->for($this->site)->create(['domain' => 'example.com', 'status' => 'completed', 'location_code' => 2826, 'language_code' => 'en', 'snapshot_date' => today()]);
    SeoKeyword::factory()->for($snapshot, 'snapshot')->create(['keyword' => 'commercial heating sheffield', 'position' => 18, 'ranking_url' => 'https://example.com/heating']);
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    expect($context['baseline_report']['keywords'][0]['term'])->toBe('commercial heating sheffield')
        ->and($context['baseline_report']['keywords'][0]['baseline']['position'])->toBe(18);
});

test('Search Console context uses only the current property and keeps averages separate from ranks', function (): void {
    $connection = SearchConsoleConnection::factory()->for($this->site)->create(['property_url' => 'sc-domain:example.com']);
    SearchConsoleMetric::factory()->for($this->site)->create(['search_console_connection_id' => $connection->id, 'property_url' => 'sc-domain:example.com', 'dimension_key' => 'query:heating', 'query' => 'commercial heating', 'month' => now()->subMonth()->startOfMonth(), 'position' => 19]);
    SearchConsoleMetric::factory()->for($this->site)->create(['search_console_connection_id' => $connection->id, 'property_url' => 'sc-domain:old.example', 'property_hash' => hash('sha256', 'sc-domain:old.example'), 'dimension_key' => 'query:old', 'query' => 'private old query', 'month' => now()->subMonth()->startOfMonth()]);
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    expect($context['search_console']['recent_queries'])->toHaveCount(1)
        ->and($context['baseline_report']['keywords'][0]['baseline'])->toBeNull()
        ->and($context['baseline_report']['keywords'][0]['search_console']['position'])->toBe(19.0);
    expect(json_encode($context))->not->toContain('private old query', 'access_token', 'refresh_token');
});

test('report text is escaped and plan navigation preserves the website context', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['published_at' => now()]);
    $report = $plan->report;
    $report['summary'] = '<script>alert("unsafe")</script>';
    $plan->update(['report' => $report]);
    $this->get($plan->shareUrl())->assertSuccessful()->assertSee('<script>alert("unsafe")</script>')->assertDontSee('<script>alert("unsafe")</script>', false);
    expect(WebsiteNavigation::routeFor($this->site, 'plan'))->toBe(route('admin.website-growth-plans.index', $this->site));
    $this->actingAs($this->staff)->post(route('admin.current-website.update'), ['website_id' => $this->site->id, 'section' => 'plan'])
        ->assertRedirect(route('admin.website-growth-plans.index', $this->site));
    $this->get(route('admin.websites.section', [$this->site, 'plan']))->assertSuccessful();
});

test('shared plans are standalone static documents with concise legacy content', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['published_at' => now()]);
    $report = $plan->report;
    $report['summary'] = 'Improve the pages that turn relevant searches into enquiries. '.str_repeat('This historical introduction is much too long. ', 30);
    $report['roadmap'] = array_fill(0, 6, str_repeat('Improve the relevant pages with clear evidence and useful answers. ', 12));
    $report['health'] = ['failed' => 2, 'warnings' => 3, 'completed_at' => now()->toIso8601String(), 'findings' => []];
    $report['keywords'] = collect(range(1, 12))->map(fn (int $index): array => [
        'term' => 'heating keyword '.$index, 'intended_url' => 'https://example.com/service-'.$index,
        'assignment_role' => $index === 12 ? 'primary' : 'supporting', 'action' => 'Improve this existing service page with verified installation examples.',
        'baseline' => ['status' => 'ranked', 'position' => $index, 'observed_at' => now()->toIso8601String()],
        'target_best' => null, 'target_worst' => null,
    ])->all();
    $plan->update(['report' => $report]);
    $response = $this->get($plan->shareUrl())->assertSuccessful()
        ->assertSee('Priority searches selected for this plan.')->assertSee('Focus searches already in Google’s top 10.')->assertSee('Audit warnings and failed checks to review.')
        ->assertSee('marketing-site', false)->assertSee('@page', false)->assertSee('print:break-inside-avoid', false)
        ->assertSee('This historical introduction')->assertDontSee('admin/websites', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input|//select|//textarea|//details|//summary|//a|//script|//nav|//iframe'))->toHaveCount(0)
        ->and($xpath->query('//dialog'))->toHaveCount(1)
        ->and($xpath->query('//form[@method="dialog"]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-plan-priority]'))->toHaveCount(3)
        ->and($xpath->query('//*[@data-plan-keyword]'))->toHaveCount(6)
        ->and($xpath->query('//*[@data-plan-milestone]'))->toHaveCount(6);
    foreach ($xpath->query('//*[@data-plan-milestone]') as $milestone) {
        expect(str_word_count($milestone->textContent))->toBeLessThanOrEqual(24);
    }
    $response->assertSee('heating keyword 12');
});

test('AI plans reject long introductions and invented shortlist ids', function (): void {
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    WebsiteGrowthPlanner::fake([[...generatedGrowthPlanResponse(), 'summary' => str_repeat('Too much text. ', 40)]]);
    expect(fn () => app(WebsiteGrowthPlanBuilder::class)->generate($context))->toThrow(ValidationException::class);
    WebsiteGrowthPlanner::fake([[...generatedGrowthPlanResponse(), 'focus_keyword_ids' => [999]]]);
    expect(fn () => app(WebsiteGrowthPlanBuilder::class)->generate($context))->toThrow(ValidationException::class);
});

test('the AI shortlist controls the report without changing saved ranking evidence', function (): void {
    $context = app(WebsiteGrowthPlanBuilder::class)->snapshot($this->site);
    $context['baseline_report']['keywords'] = collect(range(0, 8))->map(fn (int $id): array => [
        'keyword_id' => $id, 'term' => 'heating '.$id, 'assignment_role' => 'supporting', 'intended_url' => null,
        'baseline' => ['status' => 'ranked', 'position' => $id + 10, 'observed_at' => now()->toIso8601String()],
    ])->all();
    WebsiteGrowthPlanner::fake([[...generatedGrowthPlanResponse(range(0, 8)), 'focus_keyword_ids' => [8, 4, 2]]]);
    $report = app(WebsiteGrowthPlanBuilder::class)->generate($context);
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['report' => $report]);
    $presentation = app(WebsiteGrowthPlanPresentation::class)->for($plan);
    expect(array_column($presentation['keywords'], 'keyword_id'))->toBe([8, 4, 2])
        ->and($presentation['keywords'][0]['baseline']['position'])->toBe(18)
        ->and($report['summary'])->toBe(generatedGrowthPlanResponse()['summary']);
});

test('long hero copy has complete excerpts with full text in accessible dialogs and print output', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['published_at' => now()]);
    $report = $plan->report;
    $report['summary'] = str_repeat('The website has relevant search opportunities and important pages to improve ', 10).'for more bookings.';
    $report['success_measure'] = str_repeat('Success should be measured through relevant searches and qualified enquiries ', 10).'with completed bookings.';
    $plan->update(['report' => $report]);
    $response = $this->get($plan->shareUrl())->assertSuccessful()->assertSee('Read more');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['intro' => 'summary', 'goal' => 'success_measure'] as $key => $field) {
        $excerpt = $xpath->query('//*[@data-plan-'.$key.']')->item(0)->textContent;
        expect($excerpt)->toEndWith('.')->not->toContain('…');
        expect($xpath->query('//dialog[@id="plan-'.$key.'-dialog"]'))->toHaveCount(1);
        expect($xpath->query('//dialog[@id="plan-'.$key.'-dialog"]/div/p')->item(0)->textContent)->toBe($report[$field]);
        expect($xpath->query('//*[@data-plan-'.$key.'-print]')->item(0)->textContent)->toBe($report[$field]);
        expect($xpath->query('//button[@aria-controls="plan-'.$key.'-dialog"]'))->toHaveCount(1);
    }
});

test('short complete hero text needs no read more controls', function (): void {
    $plan = WebsiteGrowthPlan::factory()->for($this->site)->create(['published_at' => now()]);
    $this->get($plan->shareUrl())->assertSuccessful()->assertDontSee('Read more')->assertDontSee('<dialog', false);
});

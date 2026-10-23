<?php

use App\Models\BacklinkAudit;
use App\Models\BacklinkOpportunity;
use App\Models\CompetitorAudit;
use App\Models\CompetitorKeyword;
use App\Models\CompetitorOpportunity;
use App\Models\CompetitorPage;
use App\Models\ContentGeneration;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\RemediationRun;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use App\Models\SearchConsoleMetric;
use App\Models\SearchOpportunity;
use App\Models\SeoImpact;
use App\Models\SeoImpactReview;
use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteLocalProject;
use App\Models\WebsiteSetup;
use App\Services\AgentSeoEvidence;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->website = Website::factory()->create(['name' => 'Rowglo evidence', 'webhook_secret' => 'WEBHOOK SECRET']);
    $this->admin->assignedWebsites()->attach($this->website);
    $this->token = str_repeat('agent-evidence-', 4);
    config(['services.sitewell_agent.token_hash' => hash('sha256', $this->token), 'services.sitewell_agent.user_id' => $this->admin->id]);
    $this->connection = SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:rowglo.co.uk', 'access_token' => 'ACCESS TOKEN', 'refresh_token' => 'REFRESH TOKEN']);
    $this->withToken($this->token);
});

test('website directory exposes only accessible websites and their host project mappings', function (): void {
    $private = Website::factory()->create(['name' => 'PRIVATE WEBSITE']);
    WebsiteLocalProject::factory()->for($private)->create(['path' => '/private/SECRET PATH']);
    WebsiteLocalProject::factory()->for($this->website)->create(['path' => '/Users/example/Code/rowglo']);
    $response = $this->getJson(route('agent.websites.index', ['search' => 'Rowglo']))->assertSuccessful()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.local_projects.0.path', '/Users/example/Code/rowglo')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect($response->getContent())->not->toContain('PRIVATE WEBSITE', 'SECRET PATH', 'WEBHOOK SECRET');
    $this->admin->assignedWebsites()->detach($this->website);
    $this->getJson(route('agent.websites.index'))->assertSuccessful()->assertJsonCount(0, 'data');
});

test('website catalogue provides dated source coverage business facts and source links without credentials', function (): void {
    WebsiteSetup::factory()->for($this->website)->create(['data' => ['business' => ['services' => 'Boiler installation', 'locations' => 'Doncaster'], 'goals' => ['objective' => 'More boiler enquiries'], 'delivery' => ['email_recipients' => 'PRIVATE EMAIL'], 'google' => ['access_token' => 'PRIVATE TOKEN']]]);
    SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'boiler installation doncaster']);
    $response = $this->getJson(route('agent.websites.show', $this->website))->assertSuccessful()
        ->assertJsonPath('business_context.business.locations', 'Doncaster')
        ->assertJsonPath('evidence.targets.stored_rows', 1)
        ->assertJsonPath('evidence.targets.keyword_filter_supported', true)
        ->assertJsonPath('search_console.property_url', 'sc-domain:rowglo.co.uk');
    expect($response->json('evidence.targets.latest_recorded_date'))->not->toBeNull();
    expect($response->getContent())->not->toContain('ACCESS TOKEN', 'REFRESH TOKEN', 'WEBHOOK SECRET', 'PRIVATE TOKEN', 'PRIVATE EMAIL');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('every SEO evidence section reads its own stored records with provenance and pagination', function (string $section): void {
    $target = SeoTargetKeyword::factory()->for($this->website)->create();
    $snapshot = SeoSnapshot::factory()->for($this->website)->create(['status' => 'completed']);
    $audit = CompetitorAudit::factory()->for($this->website)->create(['status' => 'completed']);
    $report = WebsiteHealthReport::factory()->for($this->website)->create(['status' => 'completed', 'completed_at' => now()]);
    $impact = SeoImpact::factory()->for($this->website)->create();
    $backlink = BacklinkAudit::factory()->for($this->website)->create();
    $plan = ContentPlan::factory()->for($this->website)->create();
    match ($section) {
        'targets', 'snapshots', 'competitor-audits', 'health-reports', 'impacts', 'backlink-audits' => null,
        'rankings' => SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $this->website->id]),
        'keywords' => SeoKeyword::factory()->for($snapshot, 'snapshot')->create(['website_id' => $this->website->id]),
        'search-console' => SearchConsoleMetric::factory()->for($this->website)->create(['property_url' => $this->connection->property_url, 'property_hash' => hash('sha256', $this->connection->property_url)]),
        'search-console-daily' => SearchConsoleDailyMetric::factory()->for($this->website)->create(['date' => '2026-10-19', 'property_url' => $this->connection->property_url, 'property_hash' => hash('sha256', $this->connection->property_url)]),
        'health-pages' => WebsiteHealthReportPage::factory()->for($report, 'report')->create(),
        'competitor-keywords' => CompetitorKeyword::factory()->for($audit, 'audit')->create(),
        'competitor-pages' => CompetitorPage::factory()->for($audit, 'audit')->create(),
        'search-opportunities' => SearchOpportunity::factory()->for($this->website)->create(),
        'seo-opportunities' => SeoOpportunity::factory()->for($this->website)->create(),
        'content-opportunities' => ContentOpportunity::factory()->for($this->website)->create(),
        'backlink-opportunities' => BacklinkOpportunity::factory()->for($this->website)->create(['backlink_audit_id' => $backlink->id]),
        'impact-reviews' => SeoImpactReview::factory()->for($impact, 'impact')->create(),
        'competitor-opportunities' => CompetitorOpportunity::factory()->for($this->website)->create(['competitor_audit_id' => $audit->id]),
        'remediation-runs' => RemediationRun::factory()->for($report, 'report')->create(['prompt' => 'PRIVATE PROMPT']),
        'content-requests' => ContentRequest::factory()->for($this->website)->create(['manual_prompt' => 'PRIVATE PROMPT']),
        'content-runs' => ContentGeneration::factory()->for($plan, 'plan')->create(['prompt' => 'PRIVATE PROMPT']),
    };
    $response = $this->getJson(route('agent.websites.evidence', [$this->website, $section]))->assertSuccessful()
        ->assertJsonPath('read_only', true)->assertJsonPath('pagination.total', 1)->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['evidence_id', 'source', 'record', 'sitewell_url', 'payload_limited']]]);
    expect($response->getContent())->not->toContain('PRIVATE PROMPT', 'ACCESS TOKEN', 'REFRESH TOKEN', 'WEBHOOK SECRET');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with(AgentSeoEvidence::SECTIONS);

test('keyword rankings retain intended URLs observed dates and market dimensions', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'boiler installation doncaster', 'intended_url' => 'https://rowglo.co.uk/boilers/']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $this->website->id, 'position' => 14, 'observed_at' => '2026-10-20 10:00:00']);
    SeoTargetKeywordRanking::factory()->for($target, 'targetKeyword')->create(['website_id' => $this->website->id, 'position' => 7, 'location_code' => 2840]);
    $this->getJson(route('agent.websites.evidence', [$this->website, 'rankings', 'keyword' => 'BOILER INSTALLATION DONCASTER', 'location_code' => 2826, 'device' => 'desktop', 'from' => '2026-10-01', 'to' => '2026-10-21']))
        ->assertSuccessful()->assertJsonPath('keyword_filter_applied', true)->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('data.0.record.position', 14)->assertJsonPath('data.0.record.target_keyword.intended_url', 'https://rowglo.co.uk/boilers/');
});

test('Search Console isolates current property samples and leaves missing evidence unknown', function (): void {
    foreach (['sc-domain:rowglo.co.uk', 'sc-domain:old-property.example'] as $property) {
        SearchConsoleMetric::factory()->for($this->website)->create(['property_url' => $property, 'property_hash' => hash('sha256', $property), 'month' => '2026-09-01', 'query' => 'boiler installation doncaster', 'dimension_key' => hash('sha256', $property)]);
    }
    $this->getJson(route('agent.websites.evidence', [$this->website, 'search-console', 'keyword' => 'boiler installation doncaster']))
        ->assertSuccessful()->assertJsonCount(1, 'data')->assertJsonPath('data.0.record.property_url', 'sc-domain:rowglo.co.uk');
    $this->getJson(route('agent.websites.evidence', [$this->website, 'search-console', 'keyword' => 'nonexistent phrase']))
        ->assertSuccessful()->assertJsonPath('pagination.total', 0)->assertJsonCount(0, 'data');
    $this->connection->update(['property_url' => 'sc-domain:new.example']);
    $this->getJson(route('agent.websites.evidence', [$this->website, 'search-console']))->assertSuccessful()->assertJsonCount(0, 'data');
});

test('daily search evidence excludes unfinalised recent dates and preserves null no data metrics', function (): void {
    foreach (['2026-10-19', '2026-10-23'] as $date) {
        SearchConsoleDailyMetric::factory()->for($this->website)->create(['date' => $date, 'data_status' => 'no_data', 'clicks' => null, 'impressions' => null, 'position' => null, 'property_url' => $this->connection->property_url, 'property_hash' => hash('sha256', $this->connection->property_url)]);
    }
    $this->getJson(route('agent.websites.evidence', [$this->website, 'search-console-daily']))->assertSuccessful()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.record.clicks', null)->assertJsonPath('keyword_filter_applied', false);
});

test('evidence pagination preserves literal keyword and date filters', function (): void {
    SeoTargetKeyword::factory()->count(3)->for($this->website)->sequence(['term' => 'Boiler 100% safe'], ['term' => '100% boiler'], ['term' => 'Boiler different'])->create();
    $response = $this->getJson(route('agent.websites.evidence', [$this->website, 'targets', 'keyword' => '100%', 'per_page' => 1]))
        ->assertSuccessful()->assertJsonPath('pagination.total', 2)->assertJsonPath('pagination.has_more', true)->assertJsonCount(1, 'data');
    $this->getJson($response->json('pagination.next_url'))->assertSuccessful()->assertJsonPath('pagination.page', 2)->assertJsonPath('pagination.has_more', false);
});

test('evidence routes reject inaccessible sites customers mutations and unsupported sections', function (): void {
    $private = Website::factory()->create();
    foreach (['agent.websites.show', 'agent.websites.evidence'] as $route) {
        $this->getJson(route($route, [$private, 'targets']))->assertNotFound();
    }
    $this->getJson(route('agent.websites.evidence', [$this->website, 'credentials']))->assertNotFound();
    $this->postJson(route('agent.websites.show', $this->website))->assertMethodNotAllowed();
    $this->admin->update(['role' => User::ROLE_USER]);
    $this->getJson(route('agent.websites.show', $this->website))->assertForbidden();
});

test('cross site parent IDs cannot expose health competitor or impact evidence', function (string $section): void {
    $private = Website::factory()->create();
    $parent = match ($section) {
        'health-pages' => WebsiteHealthReport::factory()->for($private)->create(['status' => 'completed']),
        'competitor-keywords' => CompetitorAudit::factory()->for($private)->create(),
        'impact-reviews' => SeoImpact::factory()->for($private)->create(),
    };
    match ($section) {
        'health-pages' => WebsiteHealthReportPage::factory()->for($parent, 'report')->create(),
        'competitor-keywords' => CompetitorKeyword::factory()->for($parent, 'audit')->create(),
        'impact-reviews' => SeoImpactReview::factory()->for($parent, 'impact')->create(),
    };
    $this->getJson(route('agent.websites.evidence', [$this->website, $section, 'parent_id' => $parent->id]))->assertSuccessful()->assertJsonCount(0, 'data');
})->with(['health-pages', 'competitor-keywords', 'impact-reviews']);

test('invalid bounded filters fail validation', function (array $filter): void {
    $this->getJson(route('agent.websites.evidence', [$this->website, 'targets', ...$filter]))->assertUnprocessable();
})->with([
    [['per_page' => 51]], [['page' => 0]], [['from' => 'bad date']], [['from' => '2026-10-23', 'to' => '2026-10-01']],
    [['keyword' => ['array']]], [['parent_id' => 1]], [['device' => 'desktop']],
]);

test('large nested evidence is bounded marked and stripped of credential fields', function (): void {
    ContentOpportunity::factory()->for($this->website)->create(['brief' => ['purpose' => str_repeat('é', 5000), 'access_token' => 'PRIVATE TOKEN', 'source_urls' => array_fill(0, 200, 'https://example.test/guide')]]);
    $response = $this->getJson(route('agent.websites.evidence', [$this->website, 'content-opportunities']))->assertSuccessful()->assertJsonPath('data.0.payload_limited', true);
    expect($response->getContent())->not->toContain('PRIVATE TOKEN');
    expect(strlen($response->json('data.0.record.brief.purpose')))->toBeLessThanOrEqual(4000);
});

test('CLI evidence requests use the configured API without printing credentials', function (): void {
    config(['services.sitewell_agent.endpoint' => 'https://sitewell.example/api/agent/v1/work', 'services.sitewell_agent.token' => $this->token]);
    Http::fake(['*' => Http::response(['read_only' => true, 'data' => []])]);
    $this->artisan('agent:evidence', ['website' => '24', 'section' => 'rankings', '--keyword' => 'boiler installation doncaster'])
        ->doesntExpectOutputToContain($this->token)->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://sitewell.example/api/agent/v1/websites/24/evidence/rankings')
        && $request['keyword'] === 'boiler installation doncaster' && $request->hasHeader('Authorization', 'Bearer '.$this->token));
    $this->artisan('agent:evidence', ['website' => 'https://evil.example'])->assertFailed();
    Http::assertSentCount(1);
});

test('all evidence entry points require bearer authentication independently of browser sessions', function (): void {
    $this->actingAs($this->admin)->withHeaders(['Authorization' => '']);
    $this->getJson(route('agent.websites.index'))->assertUnauthorized();
    $this->getJson(route('agent.websites.show', $this->website))->assertUnauthorized();
    $this->getJson(route('agent.websites.evidence', [$this->website, 'targets']))->assertUnauthorized();
});

test('a ranking linked to another websites target cannot leak that targets details', function (): void {
    $foreignTarget = SeoTargetKeyword::factory()->create(['term' => 'PRIVATE TARGET']);
    SeoTargetKeywordRanking::factory()->for($foreignTarget, 'targetKeyword')->create(['website_id' => $this->website->id]);
    $this->getJson(route('agent.websites.evidence', [$this->website, 'rankings']))->assertSuccessful()->assertJsonCount(0, 'data');
});

test('monthly samples mark the current partial month and retain site versus query dimensions', function (): void {
    SearchConsoleMetric::factory()->for($this->website)->create(['month' => '2026-10-01', 'property_url' => $this->connection->property_url, 'property_hash' => hash('sha256', $this->connection->property_url)]);
    $this->getJson(route('agent.websites.evidence', [$this->website, 'search-console']))->assertSuccessful()
        ->assertJsonPath('data.0.record.dimension', 'site')->assertJsonPath('data.0.record.includes_current_partial_month', true);
});

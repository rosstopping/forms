<?php

use App\Models\BacklinkAudit;
use App\Models\CompetitorAudit;
use App\Models\ContentOpportunity;
use App\Models\ReportingSnapshot;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\User;
use App\Models\Website;
use App\Models\WeeklyReport;
use App\Services\GoogleOAuthClient;
use App\Services\ReportingPeriod;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['reporting.staff_only_reports' => true]);
    Http::preventStrayRequests();
    $this->staff = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->site = Website::factory()->create(['name' => 'Rowglo']);
    $this->staff->assignedWebsites()->attach($this->site);
    $this->other = Website::factory()->create(['name' => 'Private client']);
});

test('staff land on scoped reports and retired forms cannot change records', function (): void {
    $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertRedirectToRoute('admin.reporting.index');
    $this->get(route('admin.reporting.index'))->assertOk()->assertSee('SEO overview')->assertSee('Rowglo')
        ->assertDontSee('Private client')->assertDontSee('Website builder')->assertDontSee('Outreach');
    $this->get(route('admin.reporting.site', $this->other))->assertForbidden();
    $this->get(route('admin.forms.index'))->assertGone();
    $this->put(route('admin.websites.update', $this->site), ['name' => 'Changed'])->assertGone();
    expect($this->site->fresh()->name)->toBe('Rowglo');
    $this->actingAs($this->site->owner)->get(route('admin.reporting.index'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertOk();
});

test('site report shows scoped search, keyword checks, ideas and historical weekly reports', function (): void {
    $connection = SearchConsoleConnection::factory()->for($this->site)->create(['property_url' => 'sc-domain:rowglo.co.uk']);
    SearchConsoleDailyMetric::factory()->for($this->site)->create(['search_console_connection_id' => $connection->id,
        'property_hash' => hash('sha256', $connection->property_url), 'date' => ReportingPeriod::cutoff(), 'clicks' => 792, 'data_status' => 'observed']);
    $target = SeoTargetKeyword::factory()->for($this->site)->create(['term' => 'boiler service']);
    SeoTargetKeywordRanking::factory()->create(['website_id' => $this->site->id, 'seo_target_keyword_id' => $target->id,
        'status' => 'ranked', 'position' => 4, 'location_code' => config('services.dataforseo.location_code'), 'language_code' => config('services.dataforseo.language_code'), 'device' => 'desktop', 'observed_at' => now()]);
    ContentOpportunity::factory()->for($this->site)->create(['title' => 'Boiler servicing in Doncaster']);
    $report = WeeklyReport::factory()->for($this->site)->create(['overview' => 'Recorded weekly findings']);
    $foreign = WeeklyReport::factory()->for($this->other)->create();

    $this->actingAs($this->staff)->get(route('admin.reporting.site', $this->site))->assertOk()->assertSee('792')->assertSee('1/7 days recorded')
        ->assertSee('No Google URL inspections recorded')->assertSee('No GA4 property linked')->assertSee('boiler service')->assertSee('Boiler servicing in Doncaster');
    $this->get(route('admin.reporting.index'))->assertViewHas('rows', fn ($rows) => (int) $rows->first()['ranks']->top_ten === 1);
    $this->get(route('admin.reporting.weekly', [$this->site, 'weekly_report' => $report->id]))->assertOk()->assertSee('Recorded weekly findings');
    $this->get(route('admin.reporting.weekly', [$this->site, 'weekly_report' => $foreign->id]))->assertNotFound();
    $this->get(route('admin.weekly-overviews.show', [$this->site, 'weekly_report' => $report->id]))
        ->assertRedirect(route('admin.reporting.weekly', [$this->site, 'weekly_report' => $report->id]));
});

test('sync stores separate Google sources idempotently and hides figures after property changes', function (): void {
    config(['reporting.analytics_properties' => [$this->site->id => '12345']]);
    $connection = SearchConsoleConnection::factory()->for($this->site)->create(['property_url' => 'https://rowglo.co.uk/']);
    $this->mock(GoogleOAuthClient::class, fn ($mock) => $mock->shouldReceive('accessToken')->andReturn('fixture'));
    Http::fake(['searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Indexed']]]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['metricValues' => [['value' => '1432'], ['value' => '0']]]]])]);
    $this->artisan('reporting:sync', ['website' => $this->site->id])->assertSuccessful();
    $this->artisan('reporting:sync', ['website' => $this->site->id])->assertSuccessful();
    expect(ReportingSnapshot::count())->toBe(2);
    $this->actingAs($this->staff)->get(route('admin.reporting.site', $this->site))->assertOk()->assertSee('1,432')
        ->assertSee('1 of 1 sampled URLs indexed')->assertSee('Indexed');
    $this->get(route('admin.reporting.index'))->assertViewHas('rows', fn ($rows) => $rows->first()['analytics']->metrics['active_users'] === 0);
    config(['reporting.analytics_properties' => [$this->site->id => '54321']]);
    $connection->update(['property_url' => 'sc-domain:rowglo.co.uk']);
    $this->get(route('admin.reporting.site', $this->site))->assertDontSee('1,432')->assertSee('Awaiting GA4 data')->assertSee('No Google URL inspections recorded');
    Http::assertSentCount(4);
});

test('staff can open stored audit evidence without paid run or queue forms', function (): void {
    $audit = CompetitorAudit::factory()->for($this->site)->create(['status' => 'completed']);
    $backlinks = BacklinkAudit::factory()->for($this->site)->create(['status' => 'completed']);
    $this->actingAs($this->staff)->get(route('admin.competitor-audits.show', [$this->site, $audit]))->assertOk()
        ->assertDontSee('Refresh audit')->assertDontSee('Queue content brief');
    $this->get(route('admin.backlink-audits.show', [$this->site, $backlinks]))->assertOk()
        ->assertDontSee('action="'.route('admin.backlink-audits.store', $this->site).'"', false);
    $this->post(route('admin.backlink-audits.store', $this->site))->assertGone();
    Http::assertNothingSent();
});

test('analytics permission is only requested for a mapped property', function (): void {
    config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
    $connect = route('admin.search-console.connect', $this->site);
    $redirect = $this->actingAs($this->staff)->get($connect)->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $original);
    expect($original['scope'])->not->toContain('analytics.readonly');
    config(['reporting.analytics_properties' => [$this->site->id => '12345']]);
    $redirect = $this->get($connect)->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $mapped);
    expect($mapped['scope'])->toContain('webmasters.readonly', 'analytics.readonly');
    Http::assertNothingSent();
});

test('Codex sees overdue reporting for assigned sites without making a provider request', function (): void {
    $this->site->update(['weekly_ranking_reports_enabled' => true]);
    $this->other->update(['weekly_ranking_reports_enabled' => true]);
    $token = str_repeat('reporting-token-', 4);
    config(['services.sitewell_agent.token_hash' => hash('sha256', $token), 'services.sitewell_agent.user_id' => $this->staff->id]);
    $this->withToken($token)->getJson(route('agent.work'))->assertOk()
        ->assertJsonFragment(['title' => 'Weekly report overdue'])->assertDontSee('Private client');
    Http::assertNothingSent();
});

test('unavailable Google totals do not become zeros or erase a successful inspection', function (): void {
    config(['reporting.analytics_properties' => [$this->site->id => '12345']]);
    SearchConsoleConnection::factory()->for($this->site)->create(['property_url' => 'https://rowglo.co.uk/']);
    $this->mock(GoogleOAuthClient::class, fn ($mock) => $mock->shouldReceive('accessToken')->andReturn('fixture'));
    Http::fake(['searchconsole.googleapis.com/*' => Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL']]]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []])]);
    $this->artisan('reporting:sync', ['website' => $this->site->id])->assertFailed();
    expect(ReportingSnapshot::where('source', 'analytics')->count())->toBe(0);
    $this->actingAs($this->staff)->get(route('admin.reporting.index'))->assertOk()->assertSee('Review Google index verdicts')->assertSee('GA4 totals are due');
});

<?php

use App\Ai\Agents\AuditBusinessProfiler;
use App\Ai\Agents\AuditOpportunitySelector;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditOpportunity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

function opportunityTerms(): array
{
    return [
        0 => ['id' => 0, 'term' => 'garden office doncaster', 'monthly_searches' => 1000, 'difficulty' => 20, 'observations' => [
            'oak.example' => ['position' => 4, 'url' => 'https://oak.example/offices', 'estimated_visits' => 80],
            'elm.example' => ['position' => 5, 'url' => 'https://elm.example/offices', 'estimated_visits' => 60],
            'ash.example' => ['position' => 6, 'url' => 'https://ash.example/offices', 'estimated_visits' => 50],
        ]],
        1 => ['id' => 1, 'term' => 'garden offices doncaster', 'monthly_searches' => 800, 'difficulty' => 20, 'observations' => [
            'oak.example' => ['position' => 4, 'url' => 'https://oak.example/offices', 'estimated_visits' => 64],
            'elm.example' => ['position' => 5, 'url' => 'https://elm.example/offices', 'estimated_visits' => 48],
            'ash.example' => ['position' => 6, 'url' => 'https://ash.example/offices', 'estimated_visits' => 40],
        ]],
    ];
}

function opportunityComparables(): array
{
    return array_map(fn (string $domain): array => ['domain' => $domain, 'reason' => 'Same service and market.', 'evidence' => 'Garden offices in Doncaster'], ['oak.example', 'elm.example', 'ash.example']);
}

function opportunityGroups(): array
{
    return [['label' => 'Garden offices in Doncaster', 'keyword_ids' => [0, 1], 'reason' => 'An evidenced service and location.']];
}

it('models new topics for a low visibility website without doubling close variants', function (): void {
    $service = app(MarketingAuditOpportunity::class);
    $seo = ['estimated_monthly_visits' => 0, 'organic_keywords' => 0, 'keywords' => []];
    $projection = $service->calculate($seo, opportunityTerms(), opportunityComparables(), opportunityGroups());

    expect($projection['six_month_low'])->toBe(3)
        ->and($projection['six_month_high'])->toBe(10)
        ->and($projection['pages'])->toHaveCount(1)
        ->and($projection['pages'][0]['modelled_search_volume'])->toBe(1000)
        ->and($projection['method'])->toContain('not calibrated');

    $duplicated = $service->calculate($seo, opportunityTerms(), opportunityComparables(), [...opportunityGroups(), ...opportunityGroups()]);
    expect($duplicated['six_month_high'])->toBe(10)->and($duplicated['pages'])->toHaveCount(1);
});

it('counts only incremental gains on existing sampled rankings', function (): void {
    $projection = app(MarketingAuditOpportunity::class)->calculate([
        'estimated_monthly_visits' => 20, 'organic_keywords' => 100,
        'keywords' => [['term' => 'garden office doncaster', 'position' => 20]],
    ], opportunityTerms(), opportunityComparables(), opportunityGroups());
    expect($projection['six_month_low'])->toBe(20)
        ->and($projection['six_month_high'])->toBe(52)
        ->and($projection['pages'][0]['work'])->toBe('Improve an existing ranking page');
});

it('caps additional visits at comparable relevant demand while preserving the wider baseline', function (): void {
    $terms = opportunityTerms();
    foreach ($terms[0]['observations'] as &$observation) {
        $observation['estimated_visits'] = 15;
    }
    unset($observation);
    $projection = app(MarketingAuditOpportunity::class)->calculate(['estimated_monthly_visits' => 10, 'organic_keywords' => 100, 'keywords' => []], $terms, opportunityComparables(), opportunityGroups());
    expect($projection['six_month_high'])->toBe(25)->and($projection['benchmark_ceiling'])->toBe(15.0);
});

it('refuses invented comparison ids, hard new keywords and unsupported demand', function (): void {
    $service = app(MarketingAuditOpportunity::class);
    $seo = ['estimated_monthly_visits' => 0, 'organic_keywords' => 0, 'keywords' => []];
    expect($service->calculate($seo, opportunityTerms(), array_slice(opportunityComparables(), 0, 2), opportunityGroups()))->toBeNull()
        ->and($service->calculate($seo, opportunityTerms(), opportunityComparables(), [['label' => 'Invented', 'reason' => 'Invented', 'keyword_ids' => [999]]]))->toBeNull();
    $terms = opportunityTerms();
    foreach ($terms as &$term) {
        $term['difficulty'] = 90;
    }
    unset($term);
    expect($service->calculate($seo, $terms, opportunityComparables(), opportunityGroups()))->toBeNull();
    $terms = opportunityTerms();
    foreach ($terms as &$term) {
        $term['term'] = 'oak.example login';
    }
    unset($term);
    expect($service->calculate($seo, $terms, opportunityComparables(), opportunityGroups()))->toBeNull();
});

it('does not make provider calls when business evidence or credentials are missing', function (): void {
    Http::preventStrayRequests();
    AuditBusinessProfiler::fake()->preventStrayPrompts();
    $result = app(MarketingAuditOpportunity::class)->forSite('example.com', '', null);
    expect($result['status'])->toBe('unavailable')->and($result['projection'])->toBeNull();
    Http::assertNothingSent();
    AuditBusinessProfiler::assertNeverPrompted();
});

it('researches and caches evidenced local comparables even with no existing rankings', function (): void {
    config()->set(['services.dataforseo.login' => 'fake', 'services.dataforseo.password' => 'fake', 'ai.default' => 'openai', 'ai.providers.openai.key' => 'fake']);
    Cache::flush();
    $profile = ['supported' => true, 'name' => 'Northfield', 'market' => 'local', 'location' => 'Doncaster', 'market_evidence' => 'Garden offices in Doncaster', 'seeds' => [['term' => 'garden offices doncaster', 'evidence' => 'Garden offices in Doncaster']]];
    AuditBusinessProfiler::fake([$profile])->preventStrayPrompts();
    AuditOpportunitySelector::fake([['comparables' => opportunityComparables(), 'groups' => opportunityGroups()]])->preventStrayPrompts();
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/serp/')) {
            $items = array_map(fn (array $comparison): array => ['type' => 'organic', 'domain' => $comparison['domain'], 'url' => 'https://'.$comparison['domain'], 'title' => 'Garden offices in Doncaster', 'description' => 'Local independent supplier.'], opportunityComparables());
            $items[] = ['type' => 'organic', 'domain' => 'yell.com', 'title' => 'Directory'];
        } else {
            $domain = $request->data()[0]['target'];
            $items = $domain === 'northfield.example' ? [] : array_map(fn (array $term): array => [
                'keyword_data' => ['keyword' => $term['term'], 'location_code' => 2826, 'language_code' => 'en', 'keyword_info' => ['search_volume' => $term['monthly_searches']], 'keyword_properties' => ['keyword_difficulty' => $term['difficulty']]],
                'ranked_serp_element' => ['serp_item' => ['rank_group' => $term['observations'][$domain]['position'], 'url' => $term['observations'][$domain]['url'], 'etv' => $term['observations'][$domain]['estimated_visits']]],
            ], opportunityTerms());
        }

        return Http::response(['status_code' => 20000, 'tasks' => [['status_code' => 20000, 'id' => 'source-task', 'cost' => 0.01, 'result' => [['items' => $items]]]]]);
    });
    $seo = ['location_code' => 2826, 'language_code' => 'en', 'estimated_monthly_visits' => 0, 'organic_keywords' => 0, 'keywords' => []];
    $context = 'Northfield builds Garden offices in Doncaster. We are an independent local garden office supplier serving Doncaster.';
    $service = app(MarketingAuditOpportunity::class);
    $result = $service->forSite('northfield.example', $context, $seo);
    expect($result['status'])->toBe('ready')->and($result['comparables'])->toHaveCount(3)
        ->and($result['requests'])->toHaveCount(5)->and($result['projection']['six_month_high'])->toBe(10);
    $again = $service->forSite('northfield.example', $context, $seo, retryUnavailable: true);
    expect($again['cached'])->toBeTrue();
    Http::assertSentCount(5);
});

it('does not trust an invented service-area quote', function (): void {
    config()->set(['services.dataforseo.login' => 'fake', 'services.dataforseo.password' => 'fake', 'ai.default' => 'openai', 'ai.providers.openai.key' => 'fake']);
    AuditBusinessProfiler::fake([['supported' => true, 'name' => 'Shop', 'market' => 'national', 'location' => '', 'market_evidence' => 'We deliver nationwide', 'seeds' => []]])->preventStrayPrompts();
    Http::preventStrayRequests();
    $result = app(MarketingAuditOpportunity::class)->forSite('unverified.example', str_repeat('No information about delivery here. ', 4), ['location_code' => 2826, 'language_code' => 'en']);
    expect($result['status'])->toBe('unavailable')->and($result['projection'])->toBeNull();
    Http::assertNothingSent();
});

it('keeps the supported broader range and its evidence behind the report gate', function (): void {
    Http::preventStrayRequests();
    $projection = app(MarketingAuditOpportunity::class)->calculate(['estimated_monthly_visits' => 0, 'organic_keywords' => 0, 'keywords' => []], opportunityTerms(), opportunityComparables(), opportunityGroups());
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'findings' => [['severity' => 'passed']], 'insights' => ['opportunity' => ['status' => 'ready', 'projection' => $projection, 'comparables' => opportunityComparables()]]]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')->assertDontSee('3–10')
        ->assertDontSee('oak.example')->assertDontSee('garden office doncaster')
        ->assertDontSee('What supports this estimate');
    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))->assertSuccessful()
        ->assertSee('What supports this estimate')->assertSee('oak.example')->assertSee('Garden offices in Doncaster');
    Http::assertNothingSent();
});

it('uses a labelled measured ranking scenario when broader research is unavailable', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'insights' => [
        'opportunity' => ['status' => 'unavailable', 'projection' => null],
        'seo' => ['keywords' => [['term' => 'old term', 'position' => 15, 'monthly_searches' => 1000]], 'estimated_monthly_visits' => 0],
    ]]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertViewHas('projection', fn (array $projection): bool => $projection['model'] === 'existing_rankings_v1' && $projection['six_month_high'] === 40)->assertSee('View my report')
        ->assertDontSee('Monthly visitors from Google');
});

it('does not count two page groups targeting the same comparable pages twice', function (): void {
    $groups = [
        ['label' => 'Garden office', 'reason' => 'Relevant service', 'keyword_ids' => [0]],
        ['label' => 'Garden offices', 'reason' => 'Same service', 'keyword_ids' => [1]],
    ];
    $projection = app(MarketingAuditOpportunity::class)->calculate(['estimated_monthly_visits' => 0, 'organic_keywords' => 0, 'keywords' => []], opportunityTerms(), opportunityComparables(), $groups);
    expect($projection['six_month_high'])->toBe(10)->and($projection['pages'])->toHaveCount(1);
});

it('does not add existing provider-estimated clicks again or include the prospects brand', function (): void {
    $service = app(MarketingAuditOpportunity::class);
    $seo = ['estimated_monthly_visits' => 100, 'organic_keywords' => 100, 'keywords' => [['term' => 'garden office doncaster', 'position' => 2, 'estimated_visits' => 80]]];
    expect($service->calculate($seo, opportunityTerms(), opportunityComparables(), opportunityGroups()))->toBeNull();
    $terms = opportunityTerms();
    foreach ($terms as &$term) {
        $term['term'] = 'northfield garden office doncaster';
    }
    unset($term);
    expect($service->calculate(['estimated_monthly_visits' => 0, 'brand_terms' => ['northfield']], $terms, opportunityComparables(), opportunityGroups()))->toBeNull();
});

it('fails gracefully without retrying paid discovery when the provider is unavailable', function (): void {
    config()->set(['services.dataforseo.login' => 'fake', 'services.dataforseo.password' => 'fake', 'ai.default' => 'openai', 'ai.providers.openai.key' => 'fake']);
    $context = 'We build garden offices in Doncaster. Our independent company serves customers in Doncaster with bespoke offices.';
    AuditBusinessProfiler::fake(fn (): array => ['supported' => true, 'name' => 'Test', 'market' => 'local', 'location' => 'Doncaster', 'market_evidence' => 'garden offices in Doncaster', 'seeds' => [['term' => 'garden office doncaster', 'evidence' => 'garden offices in Doncaster']]])->preventStrayPrompts();
    AuditOpportunitySelector::fake()->preventStrayPrompts();
    Http::fake(['*' => Http::response([], 500)]);
    $service = app(MarketingAuditOpportunity::class);
    $seo = ['location_code' => 2826, 'language_code' => 'en'];
    $result = $service->forSite('failure.example', $context, $seo);
    expect($result['status'])->toBe('unavailable')->and($result['projection'])->toBeNull();
    $again = $service->forSite('failure.example', $context, $seo);
    expect($again['cached'])->toBeTrue();
    Http::assertSentCount(1);
    AuditOpportunitySelector::assertNeverPrompted();
    $retried = $service->forSite('failure.example', $context, $seo, retryUnavailable: true);
    expect($retried['status'])->toBe('unavailable')->and($retried['projection'])->toBeNull();
    Http::assertSentCount(2);
});

it('shows the measured existing-ranking scenario on an affected full report without provider calls', function (): void {
    Http::preventStrayRequests();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => [
            'opportunity' => ['status' => 'unavailable', 'projection' => null, 'reason' => 'Fewer than three supported comparable businesses.'],
            'seo' => ['keywords' => [['term' => 'garden office fitters', 'position' => 15, 'monthly_searches' => 1000]], 'estimated_monthly_visits' => 2000, 'organic_keywords' => 500, 'top_10_keywords' => 20, 'top_3_keywords' => 5, 'location_code' => 2826, 'referring_domains' => null, 'sample_size' => 1],
        ],
    ]);
    $response = $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()->assertSee('2,008–2,040')
        ->assertSee('pages already ranking just outside page one')
        ->assertDontSee('What supports this estimate')
        ->assertDontSee('Forecast diagnostic:')
        ->assertDontSee('We couldn’t produce a reliable six-month estimate');
    expect($response->viewData('projection')['model'])->toBe('existing_rankings_v1');
    Http::assertNothingSent();
});

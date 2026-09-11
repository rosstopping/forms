<?php

use App\Ai\Agents\CompetitorAnalyst;
use App\Jobs\ProcessCompetitorAuditStage;
use App\Models\CompetitorAudit;
use App\Models\CompetitorKeyword;
use App\Models\CompetitorOpportunity;
use App\Models\CompetitorPage;
use App\Models\ExternalApiUsage;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Services\CompetitorAuditService;
use App\Services\CompetitorBriefGenerator;
use App\Services\CompetitorDomain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
});

function competitorResponse(array $items): array
{
    return ['status_code' => 20000, 'tasks' => [['id' => 'test-task', 'status_code' => 20000, 'cost' => .02, 'result' => [['items' => $items, 'total_count' => count($items)]]]]];
}

test('competitors normalize domains and reject unsafe inputs', function (): void {
    expect(app(CompetitorDomain::class)->normalize('https://WWW.Example.com/path/'))->toBe('example.com');
    foreach (['localhost', 'http://127.0.0.1', 'https://user:pass@example.com', 'ftp://example.com', 'https://example.com:8080'] as $domain) {
        expect(fn () => app(CompetitorDomain::class)->normalize($domain))->toThrow(InvalidArgumentException::class);
    }
});

test('managers add exclude restore and audit competitors without spending on reads', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->actingAs($owner)->post(route('admin.competitors.store', $website), ['domain' => 'https://www.competitor.example/guide/'])->assertRedirect();
    $competitor = $website->competitors()->sole();
    $this->post(route('admin.competitors.store', $website), ['domain' => 'competitor.example'])->assertRedirect();
    expect($website->competitors()->count())->toBe(1);
    $this->get(route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors']))->assertSuccessful()->assertSee('competitor.example')->assertSee('Add competitor');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    $this->put(route('admin.competitors.update', [$website, $competitor]), ['excluded' => true])->assertRedirect();
    $this->post(route('admin.competitors.audit', [$website, $competitor]))->assertSessionHasErrors('competitor');
    $this->put(route('admin.competitors.update', [$website, $competitor]), ['excluded' => false])->assertRedirect();
    $this->post(route('admin.competitors.audit', [$website, $competitor]))->assertRedirect();
    $audit = $competitor->audits()->sole();
    $this->get(route('admin.competitor-audits.show', [$website, $audit]))->assertSuccessful()->assertSee('Audit in progress');
    $this->post(route('admin.competitors.audit', [$website, $competitor]))->assertRedirect();
    Queue::assertPushed(ProcessCompetitorAuditStage::class, 1);
    Http::assertNothingSent();
});

test('viewers cannot mutate and other websites cannot access competitor audits', function (): void {
    $competitor = WebsiteCompetitor::factory()->create();
    $audit = CompetitorAudit::factory()->for($competitor, 'competitor')->create();
    $viewer = User::factory()->create();
    $competitor->website->members()->attach($viewer, ['role' => 'viewer']);
    $this->actingAs($viewer)->get(route('admin.competitor-audits.show', [$competitor->website, $audit]))->assertSuccessful();
    $this->post(route('admin.competitors.audit', [$competitor->website, $competitor]))->assertForbidden();
    $this->post(route('admin.competitors.store', $competitor->website), ['domain' => 'other.example'])->assertForbidden();
    $other = Website::factory()->create();
    $this->actingAs($other->owner)->get(route('admin.competitor-audits.show', [$other, $audit]))->assertNotFound();
    $this->post(route('admin.competitors.audit', [$other, $competitor]))->assertNotFound();
    $competitor->website->owner->update(['membership_tier' => 'essential']);
    $this->actingAs($competitor->website->owner)->post(route('admin.competitors.audit', [$competitor->website, $competitor]))->assertRedirect(route('admin.billing.index'));
});

test('fresh audits are reused while failed audits resume their saved stage', function (): void {
    Queue::fake();
    $competitor = WebsiteCompetitor::factory()->create();
    $competitor->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $audit = CompetitorAudit::factory()->for($competitor, 'competitor')->create(['status' => 'completed', 'completed_at' => now(), 'stages' => CompetitorAuditService::STAGES]);
    expect(app(CompetitorAuditService::class)->request($competitor)->id)->toBe($audit->id);
    Queue::assertNothingPushed();
    $audit->update(['status' => 'failed', 'stages' => ['ranked_top', 'ranked_deep']]);
    app(CompetitorAuditService::class)->request($competitor);
    Queue::assertPushed(ProcessCompetitorAuditStage::class, fn ($job) => $job->stage === 'shared_keywords' && $job->audit->id === $audit->id);
});

test('domain comparisons save provider evidence and checkpoint cost once', function (): void {
    $audit = CompetitorAudit::factory()->create();
    $item = ['keyword_data' => ['keyword' => 'garden offices', 'keyword_info' => ['search_volume' => 500], 'search_intent_info' => ['main_intent' => 'commercial']], 'first_domain_serp_element' => ['rank_group' => 3, 'url' => 'https://competitor.example/guide/'], 'second_domain_serp_element' => ['rank_group' => 18, 'url' => 'https://example.com/offices/']];
    Http::fake(['*domain_intersection/live' => Http::response(competitorResponse([$item]))]);
    $service = app(CompetitorAuditService::class);
    $service->process($audit, 'shared_keywords');
    $service->process($audit, 'shared_keywords');
    expect($audit->keywords()->sole()->our_position)->toBe(18)->and($audit->keywords()->sole()->comparison)->toBe('shared')->and(ExternalApiUsage::where('competitor_audit_id', $audit->id)->count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request[0]['target1'] === $audit->competitor_domain && $request[0]['target2'] === $audit->domain && $request[0]['intersections'] === true);
    $service->process($audit, 'missing_keywords');
    expect($audit->keywords()->sole()->our_position)->toBeNull()->and($audit->keywords()->sole()->comparison)->toBe('missing');
});

test('malformed provider rows keep cost but cannot complete the stage', function (): void {
    $audit = CompetitorAudit::factory()->create();
    Http::fake(['*domain_intersection/live' => Http::response(competitorResponse([['invalid' => true]]))]);
    expect(fn () => app(CompetitorAuditService::class)->process($audit, 'shared_keywords'))->toThrow(RuntimeException::class);
    expect($audit->fresh()->stages)->not->toContain('shared_keywords')->and($audit->keywords()->count())->toBe(0)->and(ExternalApiUsage::count())->toBe(1);
});

test('briefs are constrained to supplied keyword and successfully fetched page evidence', function (): void {
    $audit = CompetitorAudit::factory()->create(['started_at' => now()]);
    $keyword = CompetitorKeyword::factory()->for($audit, 'audit')->create();
    $page = CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed', 'analysis' => ['title' => 'Garden offices', 'main_content' => 'Installation guidance']]);
    $brief = ['title' => 'Explain installation', 'primary_keyword_id' => $keyword->id, 'keyword_ids' => [$keyword->id], 'source_urls' => [$page->url], 'search_intent' => 'commercial', 'relevance' => 3, 'relevance_reason' => 'Relevant service', 'existing_page_url' => '', 'content_format' => 'Guide', 'observations' => ['Explains installation'], 'ranking_hypotheses' => ['Useful coverage may help'], 'gaps' => ['Examples'], 'improvements' => ['Original project examples'], 'outline' => ['Planning', 'Installation']];
    CompetitorAnalyst::fake([['opportunities' => [$brief, [...$brief, 'source_urls' => ['https://invented.example']]]]]);
    app(CompetitorBriefGenerator::class)->generate($audit);
    expect($audit->opportunities()->count())->toBe(1)->and($audit->opportunities()->sole()->brief['audit_id'])->toBe($audit->id);
});

test('brief generation bounds oversized analyst lists before validation', function (): void {
    $audit = CompetitorAudit::factory()->create(['started_at' => now()]);
    $keywords = CompetitorKeyword::factory()->count(15)->for($audit, 'audit')->create(['search_intent' => 'commercial']);
    $page = CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed', 'analysis' => ['title' => 'Detailed service guide']]);
    $brief = [
        'title' => 'Create a stronger service guide',
        'primary_keyword_id' => $keywords->first()->id,
        'keyword_ids' => $keywords->pluck('id')->all(),
        'source_urls' => array_fill(0, 8, $page->url),
        'search_intent' => 'commercial',
        'relevance' => 3,
        'relevance_reason' => 'Relevant to the supplied service evidence',
        'existing_page_url' => '',
        'content_format' => 'Guide',
        'observations' => collect(range(1, 8))->map(fn (int $number): string => "Observation {$number}")->all(),
        'ranking_hypotheses' => collect(range(1, 6))->map(fn (int $number): string => "Hypothesis {$number}")->all(),
        'gaps' => collect(range(1, 8))->map(fn (int $number): string => "Gap {$number}")->all(),
        'improvements' => collect(range(1, 8))->map(fn (int $number): string => "Improvement {$number}")->all(),
        'outline' => collect(range(1, 12))->map(fn (int $number): string => "Section {$number}")->all(),
    ];
    CompetitorAnalyst::fake([['opportunities' => array_fill(0, 7, $brief)]]);

    app(CompetitorBriefGenerator::class)->generate($audit);

    $saved = $audit->opportunities()->sole()->brief;
    expect($saved['keyword_ids'])->toHaveCount(10)
        ->and($saved['source_urls'])->toHaveCount(1)
        ->and($saved['observations'])->toHaveCount(5)
        ->and($saved['ranking_hypotheses'])->toHaveCount(3)
        ->and($saved['outline'])->toHaveCount(8);
});

test('brief generation accepts an opportunity without supporting keyword ids', function (): void {
    $audit = CompetitorAudit::factory()->create(['started_at' => now()]);
    $keyword = CompetitorKeyword::factory()->for($audit, 'audit')->create(['search_intent' => 'commercial']);
    $page = CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed', 'analysis' => ['title' => 'Detailed service guide']]);
    $brief = [
        'title' => 'Create a focused service guide',
        'primary_keyword_id' => $keyword->id,
        'source_urls' => [$page->url],
        'search_intent' => 'commercial',
        'relevance' => 3,
        'relevance_reason' => 'Relevant to the supplied service evidence',
        'existing_page_url' => '',
        'content_format' => 'Guide',
        'observations' => ['The competitor answers the primary service question'],
        'ranking_hypotheses' => [],
        'gaps' => ['No original examples'],
        'improvements' => ['Add verified project examples'],
        'outline' => ['Service overview'],
    ];
    CompetitorAnalyst::fake([['opportunities' => [$brief]]]);

    app(CompetitorBriefGenerator::class)->generate($audit);

    expect($audit->opportunities()->sole()->brief['keyword_ids'])->toBe([]);
});

test('all audit stages run with faked providers and retain empty evidence honestly', function (): void {
    Queue::fake();
    Http::fake(['*' => Http::response(competitorResponse([]))]);
    CompetitorAnalyst::fake()->preventStrayPrompts();
    $audit = CompetitorAudit::factory()->create();
    foreach (CompetitorAuditService::STAGES as $stage) {
        (new ProcessCompetitorAuditStage($audit->fresh(), $stage))->handle(app(CompetitorAuditService::class));
    }
    expect($audit->fresh()->status)->toBe('completed')->and($audit->fresh()->stages)->toBe(CompetitorAuditService::STAGES)->and($audit->opportunities()->count())->toBe(0)->and(ExternalApiUsage::where('competitor_audit_id', $audit->id)->count())->toBe(5);
    CompetitorAnalyst::assertNeverPrompted();
});

test('audits render keyword filters and analysed opportunities without provider calls', function (): void {
    $audit = CompetitorAudit::factory()->create(['status' => 'completed', 'completed_at' => now()]);
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'missing term']);
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'shared term', 'comparison' => 'shared', 'our_position' => 20]);
    CompetitorOpportunity::factory()->for($audit, 'audit')->create();
    $this->actingAs($audit->website->owner)->get(route('admin.competitor-audits.show', [$audit->website, $audit, 'filter' => 'missing']))->assertSuccessful()->assertSee('missing term')->assertDontSee('shared term')->assertSee('Queue content brief');
    $this->get(route('admin.competitor-audits.show', [$audit->website, $audit, 'filter' => 'outranked']))->assertSuccessful()->assertSee('shared term')->assertDontSee('missing term');
    Http::assertNothingSent();
});

test('an analyst can honestly return no relevant opportunities', function (): void {
    $audit = CompetitorAudit::factory()->create();
    CompetitorKeyword::factory()->for($audit, 'audit')->create();
    CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed', 'analysis' => ['title' => 'Unrelated service']]);
    CompetitorAnalyst::fake([['opportunities' => []]]);
    app(CompetitorBriefGenerator::class)->generate($audit);
    expect($audit->opportunities()->count())->toBe(0);
});

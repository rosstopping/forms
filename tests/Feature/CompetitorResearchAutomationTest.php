<?php

use App\Ai\Agents\CompetitorAnalyst;
use App\Jobs\ProcessCompetitorAuditStage;
use App\Jobs\StartContentGeneration;
use App\Models\CompetitorAudit;
use App\Models\CompetitorKeyword;
use App\Models\CompetitorOpportunity;
use App\Models\CompetitorPage;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Models\WebsiteRepository;
use App\Services\CompetitorAuditService;
use App\Services\CompetitorBriefGenerator;
use App\Services\CompetitorPageFetcher;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\SearchConsoleClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
    $this->travelTo(CarbonImmutable::parse('2026-09-21 08:00', 'Europe/London'));
    $this->owner = User::factory()->create(['membership_tier' => 'growth']);
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create(['competitor_research_mode' => 'drafts']);
});

function researchOpportunity(Website $website, array $brief = [], array $auditAttributes = []): CompetitorOpportunity
{
    $competitor = WebsiteCompetitor::factory()->for($website)->create();
    $audit = CompetitorAudit::factory()->for($competitor, 'competitor')->create([
        'status' => 'completed', 'completed_at' => now(), ...$auditAttributes,
    ]);

    return CompetitorOpportunity::factory()->for($audit, 'audit')->create([
        'brief' => ['title' => 'Improve our garden office guide', 'relevance' => 3, 'source_urls' => ['https://competitor.example/guide/'], 'improvements' => ['Explain installation'], 'outline' => ['Installation'], 'data_source' => 'dataforseo_estimate',
            'audit_id' => $audit->id, 'primary_keyword' => 'garden offices', 'existing_page_url' => 'https://example.com/offices/', ...$brief],
    ]);
}

test('research is bounded by tier and dispatched once per research interval', function (string $tier, int $count, int $days, int $pages) {
    $this->owner->update(['membership_tier' => $tier]);
    WebsiteCompetitor::factory()->count(7)->for($this->website)->create();
    WebsiteCompetitor::factory()->for($this->website)->create(['excluded' => true]);
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    expect(CompetitorAudit::count())->toBe($count)
        ->and(CompetitorAudit::first()->limits['analyse_pages'])->toBe($pages)
        ->and(CompetitorAudit::first()->trigger)->toBe('scheduled');
    Queue::assertPushed(ProcessCompetitorAuditStage::class, $count);
    Http::assertNothingSent();
    $this->travel($days - 1)->days();
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    expect(CompetitorAudit::count())->toBe($count);
    $this->travel(1)->days();
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    expect(CompetitorAudit::count())->toBe($count * 2);
})->with([['growth', 3, 14, 5], ['complete', 5, 7, 8]]);

test('research does not run for ineligible or manual websites', function (string $condition) {
    WebsiteCompetitor::factory()->for($this->website)->create();
    match ($condition) {
        'essential' => $this->owner->update(['membership_tier' => 'essential']),
        'expired' => $this->owner->update(['membership_status' => 'trialing', 'membership_current_period_end' => now()->subDay()]),
        'inactive' => $this->website->update(['is_active' => false]),
        'manual' => $this->plan->update(['competitor_research_mode' => 'manual']),
        'provider' => config(['services.dataforseo.login' => null]),
        'domain' => $this->website->domains()->delete(),
    };
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    expect(CompetitorAudit::count())->toBe(0)->and($this->plan->fresh()->competitor_researched_at)->toBeNull();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with(['essential', 'expired', 'inactive', 'manual', 'provider', 'domain']);

test('research only runs independently of content generation and reuses recent audits', function () {
    $this->plan->update(['enabled' => false, 'competitor_research_mode' => 'research']);
    $opportunity = researchOpportunity($this->website);
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    expect(CompetitorAudit::count())->toBe(1)->and(ContentRequest::count())->toBe(0)
        ->and($this->plan->fresh()->competitor_researched_at)->not->toBeNull();
    Queue::assertNothingPushed();
});

test('queued research stops before provider access when settings or entitlement change', function (string $condition) {
    $competitor = WebsiteCompetitor::factory()->for($this->website)->create();
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    if ($condition === 'manual') {
        $this->plan->update(['competitor_research_mode' => 'manual']);
    } else {
        $this->owner->update(['membership_tier' => 'essential']);
    }
    $audit = $competitor->audits()->sole();
    (new ProcessCompetitorAuditStage($audit, 'ranked_top'))->handle(app(CompetitorAuditService::class));
    expect($audit->fresh()->status)->toBe('failed')->and($audit->fresh()->errors)->toHaveKey('automation');
    Http::assertNothingSent();
})->with(['manual', 'essential']);

test('scheduled content selects a competitor brief and snapshots measurement scope without extra drafts', function () {
    $opportunity = researchOpportunity($this->website);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled']);
    $work = app(ContentWorkSelector::class)->select($generation);
    $request = $work['requests']->sole();
    expect($request->competitor_fingerprint)->toBe($opportunity->fingerprint)
        ->and($request->competitor_context['automatic'])->toBeTrue()
        ->and($request->seoImpact->target_urls)->toBe(['https://example.com/offices/'])
        ->and($request->seoImpact->target_queries)->toBe(['garden offices'])
        ->and($request->seoImpact->evidence['competitor_brief']['primary_keyword'])->toBe('garden offices');
    app(ContentWorkSelector::class)->select($generation);
    expect(ContentRequest::count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('queued manual requests take priority over automatic competitor briefs', function () {
    $opportunity = researchOpportunity($this->website);
    $request = ContentRequest::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->sole()->id)->toBe($request->id)
        ->and($opportunity->fresh()->status)->toBe('open')->and(ContentRequest::count())->toBe(1);
});

test('automatic briefs skip unavailable stale irrelevant and protected evidence', function (string $condition) {
    $opportunity = researchOpportunity($this->website);
    match ($condition) {
        'stale' => $opportunity->audit->update(['completed_at' => now()->subDays(31)]),
        'excluded' => $opportunity->audit->competitor->update(['excluded' => true]),
        'irrelevant' => $opportunity->update(['brief' => [...$opportunity->brief, 'relevance' => 2]]),
        'market' => $opportunity->audit->update(['location_code' => 2840]),
        'research' => $this->plan->update(['competitor_research_mode' => 'research']),
        'manual' => $this->plan->update(['competitor_research_mode' => 'manual']),
        'protected' => SeoImpact::factory()->for($this->website)->create(['status' => 'measuring', 'target_urls' => ['https://example.com/offices/'], 'target_queries' => []]),
    };
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty()
        ->and(ContentRequest::count())->toBe(0);
})->with(['stale', 'excluded', 'irrelevant', 'market', 'research', 'manual', 'protected']);

test('recent competitor content keeps its cooldown even when a new audit recommends it again', function () {
    $opportunity = researchOpportunity($this->website);
    $previous = ContentGeneration::factory()->for($this->plan, 'plan')->create([
        'status' => ContentGeneration::STATUS_COMPLETED, 'started_at' => now()->subDay(), 'copilot_task_id' => 'previous-task',
    ]);
    ContentRequest::factory()->for($this->website)->create([
        'content_generation_id' => $previous->id, 'picked_up_at' => now()->subDay(),
        'competitor_context' => ['primary_keyword' => 'garden offices'],
    ]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled', 'scheduled_for' => now()->addDay()]);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty()
        ->and($opportunity->fresh()->status)->toBe('open');
});

test('own page comparisons are checkpointed and supplied to the analyst', function () {
    $competitor = WebsiteCompetitor::factory()->for($this->website)->create();
    $audit = CompetitorAudit::factory()->for($competitor, 'competitor')->create();
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['our_ranking_url' => 'https://example.com/offices/']);
    CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed']);
    $this->mock(CompetitorPageFetcher::class)->shouldReceive('fetch')->once()->with('https://example.com/offices/', 'example.com')
        ->andReturn(['main_content' => 'Our installation service with verified project examples.']);
    $service = app(CompetitorAuditService::class);
    $service->process($audit, 'comparison_pages');
    $service->process($audit, 'comparison_pages');
    CompetitorAnalyst::fake([['opportunities' => []]]);
    app(CompetitorBriefGenerator::class)->generate($audit);
    CompetitorAnalyst::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('own_page_evidence') && $prompt->contains('verified project examples'));
    expect($audit->fresh()->comparison_pages)->toHaveCount(1);
});

test('unavailable own pages remain explicit limited evidence', function () {
    $audit = CompetitorAudit::factory()->create();
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['our_ranking_url' => 'https://example.com/offices/']);
    $this->mock(CompetitorPageFetcher::class)->shouldReceive('fetch')->once()->andThrow(new RuntimeException('Unavailable'));
    app(CompetitorAuditService::class)->process($audit, 'comparison_pages');
    expect($audit->fresh()->comparison_pages[0]['status'])->toBe('unavailable')
        ->and($audit->fresh()->comparison_pages[0]['analysis'])->toBeNull();
});

test('scheduled competitor drafts flow through the existing weekly allowance and review prompt', function () {
    researchOpportunity($this->website);
    $this->artisan('content:dispatch')->assertSuccessful();
    $generation = $this->plan->generations()->sole();
    $this->mock(SearchConsoleClient::class)->shouldNotReceive('performance');
    $this->mock(CopilotAgentClient::class)->shouldReceive('startTask')->once()->withArgs(function ($authorization, $repository, string $prompt): bool {
        return str_contains($prompt, 'garden offices') && str_contains($prompt, 'pull request');
    })->andReturn(['id' => 'competitor-task']);
    (new StartContentGeneration($generation))->handle(app(SearchConsoleClient::class), app(ContentGenerationPromptGenerator::class), app(CopilotAgentClient::class));
    expect($generation->fresh()->copilot_task_id)->toBe('competitor-task')
        ->and($generation->fresh()->competitor_context[0]['primary_keyword'])->toBe('garden offices')
        ->and(ContentRequest::sole()->picked_up_at)->not->toBeNull();
    $generation->update(['status' => ContentGeneration::STATUS_COMPLETED]);
    $this->plan->update(['weekday' => 2]);
    $this->travel(1)->days();
    $this->artisan('content:dispatch')->assertSuccessful();
    expect($this->plan->generations()->count())->toBe(1);
});

test('managers can save research settings without altering automation credentials', function () {
    $payload = ['enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London', 'competitor_research_mode' => 'research', 'content_section' => 'automation'];
    $this->actingAs($this->owner)->put(route('admin.content-plans.update', $this->website), $payload)->assertSessionDoesntHaveErrors();
    expect($this->plan->fresh()->competitor_research_mode)->toBe('research')->and($this->plan->fresh()->created_by)->toBe($this->admin->id);
    $this->get(route('admin.websites.section', [$this->website, 'section' => 'content', 'content_section' => 'automation']))
        ->assertSuccessful()->assertSee('three tracked competitors every fourteen days')->assertSee('Prepare drafts');
    $this->put(route('admin.content-plans.update', $this->website), [...$payload, 'competitor_research_mode' => 'publish'])
        ->assertSessionHasErrors('competitor_research_mode');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('plan copy describes research and retains the existing content allowances', function () {
    $this->get(route('marketing.pricing'))->assertSuccessful()
        ->assertSee('Automated competitor research to guide your content and SEO improvements')
        ->assertSee('Broader, weekly competitor research with deeper page comparisons')
        ->assertSee('Up to one scheduled content improvement per week')
        ->assertSee('Up to three scheduled content improvements per week');
});

test('research compares matching keywords across recent competitors without mixing websites or markets', function () {
    $opportunity = researchOpportunity($this->website);
    $audit = $opportunity->audit;
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'garden offices']);
    CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed']);
    foreach (['matching', 'other-market', 'excluded', 'other-website', 'unrelated-term'] as $kind) {
        $related = researchOpportunity($kind === 'other-website' ? Website::factory()->create() : $this->website);
        if ($kind === 'other-market') {
            $related->audit->update(['location_code' => 2840]);
        }
        if ($kind === 'excluded') {
            $related->audit->competitor->update(['excluded' => true]);
        }
        $url = "https://{$kind}.example/guide/";
        CompetitorKeyword::factory()->for($related->audit, 'audit')->create(['keyword' => $kind === 'unrelated-term' ? 'unrelated' : 'garden offices', 'ranking_url' => $url]);
        CompetitorPage::factory()->for($related->audit, 'audit')->create([
            'url' => $url, 'url_hash' => hash('sha256', $url), 'status' => 'completed', 'fetched_at' => now(),
            'analysis' => ['main_content' => "Evidence from {$kind}"],
        ]);
    }
    CompetitorAnalyst::fake([['opportunities' => []]]);
    app(CompetitorBriefGenerator::class)->generate($audit);
    CompetitorAnalyst::assertPrompted(function (AgentPrompt $prompt): bool {
        $context = json_decode($prompt->prompt, true);
        $urls = array_column($context['pages'], 'url');

        return count($urls) === 2 && in_array('https://matching.example/guide/', $urls, true)
            && $context['related_rankings'][0]['keyword'] === 'garden offices';
    });
    Http::assertNothingSent();
});

test('a brief must cite the actual page ranking for its primary keyword', function () {
    $audit = CompetitorAudit::factory()->create();
    $keyword = CompetitorKeyword::factory()->for($audit, 'audit')->create(['ranking_url' => 'https://competitor.example/unfetched']);
    $page = CompetitorPage::factory()->for($audit, 'audit')->create(['status' => 'completed']);
    CompetitorAnalyst::fake([['opportunities' => [[
        'title' => 'Unsupported comparison', 'primary_keyword_id' => $keyword->id, 'keyword_ids' => [],
        'source_urls' => [$page->url], 'search_intent' => 'commercial', 'relevance' => 3, 'relevance_reason' => 'Relevant service',
        'existing_page_url' => '', 'content_format' => 'Guide', 'observations' => [], 'ranking_hypotheses' => [],
        'gaps' => [], 'improvements' => ['Add details'], 'outline' => ['Details'],
    ]]]]);
    app(CompetitorBriefGenerator::class)->generate($audit);
    expect($audit->opportunities()->count())->toBe(0);
});

test('research only mode does not pick up a previously automatic draft request', function () {
    $this->plan->update(['competitor_research_mode' => 'research']);
    ContentRequest::factory()->for($this->website)->create(['competitor_context' => ['automatic' => true, 'primary_keyword' => 'garden offices']]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty();
});

test('queued research adopts reduced page and keyword limits after a downgrade', function () {
    $this->owner->update(['membership_tier' => 'complete']);
    WebsiteCompetitor::factory()->for($this->website)->create();
    $this->artisan('competitors:dispatch-research')->assertSuccessful();
    $this->owner->update(['membership_tier' => 'growth']);
    $audit = CompetitorAudit::sole();
    $this->mock(CompetitorAuditService::class)->shouldReceive('process')->once()->withArgs(fn ($audit, $stage): bool => $audit->limits['analyse_pages'] === 5 && $audit->limits['ranked_keywords'] === 100)->andReturnNull();
    app(CompetitorAuditService::class)->shouldReceive('nextStage')->once()->andReturn('ranked_deep');
    (new ProcessCompetitorAuditStage($audit, 'ranked_top'))->handle(app(CompetitorAuditService::class));
    Http::assertNothingSent();
});

test('viewers and essential accounts cannot change automatic research settings', function (string $role) {
    if ($role === 'viewer') {
        $user = User::factory()->create();
        $this->website->members()->attach($user, ['role' => Website::MEMBER_ROLE_VIEWER]);
    } else {
        $user = $this->owner;
        $user->update(['membership_tier' => 'essential']);
    }
    $response = $this->actingAs($user)->put(route('admin.content-plans.update', $this->website), [
        'enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London', 'competitor_research_mode' => 'research',
    ]);
    if ($role === 'viewer') {
        $response->assertForbidden();
    } else {
        $response->assertRedirect(route('admin.billing.index'));
    }
    expect($this->plan->fresh()->competitor_research_mode)->toBe('drafts');
})->with(['viewer', 'essential']);

test('excluded competitors cannot supply previously queued automatic requests', function () {
    $opportunity = researchOpportunity($this->website);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['trigger' => 'scheduled']);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toHaveCount(1);
    $opportunity->audit->competitor->update(['excluded' => true]);
    expect(app(ContentWorkSelector::class)->select($generation)['requests'])->toBeEmpty();
});

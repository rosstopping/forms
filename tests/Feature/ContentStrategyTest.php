<?php

use App\Jobs\StartContentGeneration;
use App\Models\CompetitorAudit;
use App\Models\CompetitorKeyword;
use App\Models\CompetitorOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\SeoImpact;
use App\Models\SeoTargetKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Models\WebsiteRepository;
use App\Services\CompetitorContentContext;
use App\Services\ContentBudget;
use App\Services\ContentDiscovery;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\SeoImpactTracker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->actingAs($this->admin);
});

test('restricted modes hold incompatible and unclassified requests while balanced preserves legacy work', function (): void {
    $unknown = ContentRequest::factory()->for($this->website)->create();
    $existing = ContentRequest::factory()->for($this->website)->create(['work_type' => 'optimisation']);
    $article = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $selector = app(ContentWorkSelector::class);
    $this->plan->update(['content_mode' => 'new_only']);
    expect($selector->queueStates($this->plan)[$unknown->id]['state'])->toBe('strategy')
        ->and($selector->queueStates($this->plan)[$existing->id]['state'])->toBe('strategy')
        ->and($selector->queueStates($this->plan)[$article->id]['state'])->toBe('ready');
    $this->plan->update(['content_mode' => 'existing_only']);
    expect($selector->queueStates($this->plan)[$article->id]['state'])->toBe('strategy')
        ->and($selector->queueStates($this->plan)[$existing->id]['state'])->toBe('ready');
    $this->plan->update(['content_mode' => 'balanced']);
    expect($selector->queueStates($this->plan)[$unknown->id]['state'])->toBe('ready');
});

test('new only cannot fall back to free choice or a keyword optimisation', function (): void {
    $this->plan->update(['content_mode' => 'new_only']);
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'manual']);
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED);
    Http::assertNothingSent();
});

test('keyword destinations must belong to the website and primary assignments are unique per page', function (): void {
    $url = route('admin.seo-target-keywords.store', $this->website);
    $this->post($url, ['term' => 'events', 'priority' => 'high', 'intended_url' => 'https://other.com/events'])->assertSessionHasErrors('intended_url');
    $this->post($url, ['term' => 'events', 'priority' => 'high', 'intended_url' => 'https://example.com/events/', 'assignment_role' => 'primary', 'search_intent' => 'commercial'])->assertSessionHasNoErrors();
    $this->post($url, ['term' => 'party events', 'priority' => 'normal', 'intended_url' => 'https://example.com/events', 'assignment_role' => 'primary'])->assertSessionHasErrors('intended_url');
    $this->post($url, ['term' => 'party events', 'priority' => 'normal', 'intended_url' => 'https://example.com/events', 'assignment_role' => 'supporting'])->assertSessionHasNoErrors();
    expect($this->website->seoTargetKeywords()->count())->toBe(2);
});

test('intended destination controls impact scope and cooldown instead of the observed ranking URL', function (): void {
    $target = SeoTargetKeyword::factory()->for($this->website)->create(['intended_url' => 'https://example.com/events']);
    SeoImpact::factory()->for($this->website)->create(['target_urls' => ['https://example.com/events'], 'live_at' => now()->subDays(1)]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->make();
    expect(app(ContentWorkSelector::class)->select($generation)['target'])->toBeNull();
    $generation->save();
    $generation->update(['seo_target_keyword_id' => $target->id, 'target_keyword_context' => [['id' => $target->id, 'term' => $target->term, 'intended_url' => $target->intended_url, 'ranking_url' => 'https://example.com/']]]);
    app(SeoImpactTracker::class)->forGeneration($generation);
    expect(SeoImpact::where('content_generation_id', $generation->id)->first()->target_urls)->toBe(['https://example.com/events']);
});

test('classification and strategy controls render and save without changing queue position', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create();
    $date = $request->created_at->toIso8601String();
    $this->patch(route('admin.content-requests.queue.update', [$this->website, $request]), ['action' => 'classify', 'work_type' => 'new_article'])->assertSessionHasNoErrors();
    expect($request->fresh()->work_type)->toBe('new_article')->and($request->fresh()->created_at->toIso8601String())->toBe($date);
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'automation']))->assertSuccessful()->assertSee('Content strategy')->assertSee('New content only');
    $this->get(route('admin.websites.section', [$this->website, 'seo', 'seo_section' => 'targets']))->assertSuccessful()->assertSee('Intended page URL');
});

test('planned content uses no execution path until explicitly approved into the queue', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create(['planning_status' => 'planned', 'work_type' => 'new_article', 'planned_for' => '2026-11-01']);
    expect($this->website->contentRequests()->pendingInQueueOrder()->count())->toBe(0);
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'plan']))->assertSuccessful()->assertSee('Rolling content plan')->assertSee('Approve into queue');
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'manual']);
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $request]))->assertUnprocessable();
    $this->patch(route('admin.content-requests.queue.update', [$this->website, $request]), ['action' => 'enqueue'])->assertSessionHasNoErrors();
    expect($this->website->contentRequests()->pendingInQueueOrder()->count())->toBe(1);
    Http::assertNothingSent();
});

test('monthly maximums reserve submitted work and reset at the plan timezone month boundary', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 9));
    $this->plan->update(['monthly_article_limit' => 1, 'monthly_copilot_limit' => 2]);
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['started_at' => now(), 'status' => 'failed', 'work_units' => ['articles' => 1, 'optimisations' => 0]]);
    $request = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $budget = app(ContentBudget::class);
    expect($budget->pauseReason($this->plan, collect([$request])))->toContain('articles maximum')
        ->and($budget->usage($this->plan)['copilot'])->toBe(1);
    $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
    expect($budget->pauseReason($this->plan, collect([$request])))->toBeNull();
});

test('zero Copilot maximum prevents paid work even for a legacy free choice task', function (): void {
    $this->plan->update(['monthly_copilot_limit' => 0]);
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'manual']);
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)->and($generation->fresh()->work_units)->toBeNull();
});

test('batch selection respects remaining article capacity without holding an independent eligible article', function (): void {
    $this->plan->update(['monthly_article_limit' => 1]);
    $first = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make();
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->modelKeys())->toBe([$first->id]);
});

test('daily discovery is opt in deduplicated and excludes irrelevant stale or wrong market evidence', function (): void {
    $competitor = WebsiteCompetitor::factory()->for($this->website)->create(['domain' => 'competitor.example']);
    $audit = CompetitorAudit::factory()->for($this->website)->for($competitor, 'competitor')->create(['status' => 'completed', 'completed_at' => now(), 'domain' => 'example.com']);
    SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning']);
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'event planning guide', 'search_intent' => 'informational']);
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'buy car insurance']);
    $discovery = app(ContentDiscovery::class);
    expect($discovery->discover($this->plan))->toBe(0);
    $this->plan->update(['discovery_enabled' => true]);
    expect($discovery->discover($this->plan))->toBe(1)->and($discovery->discover($this->plan))->toBe(0)
        ->and(ContentRequest::count())->toBe(0);
    $opportunity = CompetitorOpportunity::sole();
    expect($opportunity->brief['primary_keyword'])->toBe('event planning guide')
        ->and($opportunity->brief['requires_planning_approval'])->toBeTrue()
        ->and(app(CompetitorContentContext::class)->opportunities($this->website))->toHaveCount(0);
    $audit->update(['location_code' => 999]);
    CompetitorKeyword::factory()->for($audit, 'audit')->create(['keyword' => 'event planning checklist']);
    expect($discovery->discover($this->plan))->toBe(0);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

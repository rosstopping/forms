<?php

use App\Ai\Agents\ContentRequestPixelWriter;
use App\Enums\OptimisationStatus;
use App\Jobs\ResearchContentKeywords;
use App\Models\ContentGeneration;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\PixelPageSighting;
use App\Models\SeoImpact;
use App\Models\SeoTargetKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use App\Services\CompetitorResearchAutomation;
use App\Services\ContentBudget;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentKeywordResearch;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\ContentWorkSelector;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use App\Services\PixelUrlNormalizer;
use App\Services\WebsiteActionCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test', 'services.dataforseo.location_code' => 2826, 'services.dataforseo.language_code' => 'en', 'forms.pixel_ui_enabled' => false]);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->plan = ContentPlan::factory()->for($this->website)->create(['keyword_research_enabled' => true]);
    $this->target = SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning']);
    $this->mock(CompetitorResearchAutomation::class)->shouldReceive('allowance')->andReturn(['interval_days' => 7]);
    $this->actingAs($this->admin);
});

function relatedContentResponse(string $seed = 'event planning', int $location = 2826): DataForSEOResponse
{
    $items = collect([
        ['event planning checklist', 100, 'informational'],
        ['event planning checklist', 100, 'informational'],
        ['event planning login', 100, 'navigational'],
        ['event planning no data', null, 'informational'],
        ['car insurance guide', 2000, 'commercial'],
    ])->map(fn ($item): array => ['keyword_data' => ['keyword' => $item[0], 'location_code' => $location, 'language_code' => 'en',
        'keyword_info' => ['search_volume' => $item[1], 'last_updated_time' => '2026-10-01'], 'search_intent_info' => ['main_intent' => $item[2]]]])->all();

    return new DataForSEOResponse('dataforseo_labs/google/related_keywords/live', [['seed_keyword' => $seed, 'location_code' => $location, 'language_code' => 'en', 'items' => $items]], .02, 1, 'research-task');
}

test('keyword discovery reserves one bounded purchase and requires evidenced relevant non navigation demand', function (): void {
    $this->mock(DataForSEOClient::class)->shouldReceive('post')->once()->withArgs(function ($endpoint, $task, $timeout): bool {
        expect($this->plan->fresh()->keyword_research['status'])->toBe('pending');

        return $endpoint === 'dataforseo_labs/google/related_keywords/live' && $task['depth'] === 1 && $task['limit'] === 20 && $task['keyword'] === 'event planning' && $timeout === 30;
    })->andReturn(relatedContentResponse());
    $service = app(ContentKeywordResearch::class);
    expect($service->research($this->plan))->toBeTrue()->and($service->research($this->plan))->toBeFalse()
        ->and(ContentOpportunity::count())->toBe(1)->and(ContentRequest::count())->toBe(0)
        ->and(ContentOpportunity::sole()->brief['requires_planning_approval'])->toBeTrue()
        ->and($this->plan->fresh()->keyword_research['provider_cost_usd'])->toBe(.02);
    Queue::assertNothingPushed();
});

test('keyword discovery rotates seeds and deduplicates repeated opportunities across weeks', function (): void {
    SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event venues']);
    $seeds = [];
    $this->mock(DataForSEOClient::class)->shouldReceive('post')->twice()->andReturnUsing(function ($endpoint, $task) use (&$seeds): DataForSEOResponse {
        $seeds[] = $task['keyword'];

        return relatedContentResponse($task['keyword']);
    });
    app(ContentKeywordResearch::class)->research($this->plan);
    $this->travel(8)->days();
    app(ContentKeywordResearch::class)->research($this->plan);
    expect($seeds)->toBe(['event planning', 'event venues'])->and(ContentOpportunity::count())->toBe(1);
});

test('failed or wrong market research retains its weekly reservation without purchasing again', function (bool $timeout): void {
    $provider = $this->mock(DataForSEOClient::class)->shouldReceive('post')->once();
    if ($timeout) {
        $provider->andThrow(new RuntimeException('Uncertain provider response'));
    } else {
        $provider->andReturn(relatedContentResponse(location: 2840));
    }
    $service = app(ContentKeywordResearch::class);
    expect($service->research($this->plan))->toBeTrue()->and($service->research($this->plan))->toBeFalse()
        ->and(ContentOpportunity::count())->toBe(0)->and($this->plan->fresh()->keyword_research['status'])->toBe('failed');
})->with([true, false]);

test('queued keyword research rechecks opt in and daily discovery dispatches without Copilot', function (): void {
    $this->artisan('content:discover')->assertSuccessful();
    Queue::assertPushed(ResearchContentKeywords::class, 1);
    $this->plan->update(['keyword_research_enabled' => false]);
    $this->mock(DataForSEOClient::class)->shouldNotReceive('post');
    (new ResearchContentKeywords($this->plan))->handle(app(ContentKeywordResearch::class));
    expect($this->plan->fresh()->keyword_researched_at)->toBeNull();
    Http::assertNothingSent();
});

test('discovery follows discovered planned and approved stages with an immutable brief and impact keywords', function (): void {
    $this->mock(DataForSEOClient::class)->shouldReceive('post')->once()->andReturn(relatedContentResponse());
    app(ContentKeywordResearch::class)->research($this->plan);
    $actions = app(WebsiteActionCenter::class);
    $action = $actions->forWebsite($this->website)->sole();
    expect($action['stage'])->toBe('open')->and($action['sources'])->toBe(['discovery']);
    $request = $actions->queue($this->website, $this->admin, $action['key']);
    expect($request->planning_status)->toBe('planned')->and($request->work_type)->toBe('new_article')
        ->and($request->discovery_context['search_intent'])->toBe('informational')
        ->and($request->seoImpact->target_queries)->toBe(['event planning checklist'])
        ->and($actions->forWebsite($this->website)->sole()['stage'])->toBe('planned');
    $route = route('admin.content-requests.queue.update', [$this->website, $request]);
    $this->patch($route, ['action' => 'enqueue'])->assertSessionHasErrors('coverage_reviewed');
    expect($request->fresh()->planning_status)->toBe('planned');
    $this->patch($route, ['action' => 'enqueue', 'coverage_reviewed' => 1])->assertSessionHasNoErrors();
    expect($request->fresh()->planning_status)->toBe('queued');
    Queue::assertNothingPushed();
});

test('removing unstarted discovered work reopens its opportunity without losing evidence', function (): void {
    $this->mock(DataForSEOClient::class)->shouldReceive('post')->once()->andReturn(relatedContentResponse());
    app(ContentKeywordResearch::class)->research($this->plan);
    $actions = app(WebsiteActionCenter::class);
    $request = $actions->queue($this->website, $this->admin, $actions->forWebsite($this->website)->sole()['key']);
    $this->delete(route('admin.content-requests.destroy', [$this->website, $request]))->assertRedirect();
    expect(ContentOpportunity::sole()->status)->toBe('open')->and(ContentOpportunity::sole()->content_request_id)->toBeNull();
});

test('manual preparation enforces content ceilings independently of the Copilot allowance and retains retry usage', function (): void {
    $this->plan->update(['monthly_article_limit' => 1, 'monthly_copilot_limit' => 0]);
    $first = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article', 'instructions' => 'Create an event guide']);
    $second = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article', 'instructions' => 'Create a different article']);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $first]))->assertRedirect();
    $this->post(route('admin.content-requests.manual.take', [$this->website, $second]))->assertUnprocessable();
    $this->post(route('admin.content-requests.manual.release', [$this->website, $first]))->assertRedirect();
    $this->post(route('admin.content-requests.manual.take', [$this->website, $first]))->assertRedirect();
    expect(app(ContentBudget::class)->usage($this->plan))->toBe(['articles' => 1, 'optimisations' => 0, 'copilot' => 0]);
});

test('returning prepared work to Copilot counts its content once and protects reservations from deletion or reclassification', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create(['work_type' => 'optimisation']);
    $budget = app(ContentBudget::class);
    expect($budget->reserveRequest($request))->toBeNull()->and($budget->reserveRequest($request))->toBeNull();
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->create(['started_at' => now(), 'status' => 'failed', 'work_units' => $budget->units(collect([$request->fresh()]))]);
    expect($budget->usage($this->plan))->toBe(['articles' => 0, 'optimisations' => 1, 'copilot' => 1]);
    $this->delete(route('admin.content-requests.destroy', [$this->website, $request]))->assertUnprocessable();
    $response = $this->patchJson(route('admin.content-requests.queue.update', [$this->website, $request]), ['action' => 'classify', 'work_type' => 'new_article']);
    $response->assertUnprocessable();
    expect($request->fresh()->budget_work_type)->toBe('optimisation');
});

test('content settings and usage render without paid research and article section is validated and passed to briefs', function (): void {
    WebsiteRepository::factory()->for($this->website)->create();
    $payload = ['enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London', 'article_path' => '/guides/', 'keyword_research_enabled' => true];
    $this->put(route('admin.content-plans.update', $this->website), $payload)->assertSessionHasNoErrors();
    $this->put(route('admin.content-plans.update', $this->website), [...$payload, 'article_path' => '/../private/'])->assertSessionHasErrors('article_path');
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'automation']))->assertSuccessful()
        ->assertSee('Preferred article section')->assertSee('Discover related keyword opportunities weekly')->assertSee('This month: 0 article starts');
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make();
    expect(app(ContentGenerationPromptGenerator::class)->generate($generation))->toContain('Preferred article section: /guides/');
    Http::assertNothingSent();
});

test('Pixel queue preparation respects content ceilings before prompting without consuming Copilot tasks', function (int $limit): void {
    config(['forms.pixel_ui_enabled' => true]);
    $this->website->update(['pixel_enabled' => true]);
    $this->plan->update(['monthly_optimisation_limit' => $limit, 'monthly_copilot_limit' => 0]);
    $report = WebsiteHealthReport::factory()->for($this->website)->create(['completed_at' => now()]);
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create(['url' => 'https://example.com/services', 'meta_description' => null]);
    PixelPageSighting::factory()->for($this->website)->create(['url' => $page->url, 'url_hash' => app(PixelUrlNormalizer::class)->hash($page->url)]);
    $request = ContentRequest::factory()->for($this->website)->create(['work_type' => 'optimisation', 'instructions' => 'Improve the services page metadata']);
    ContentRequestPixelWriter::fake([['changes' => [['url' => $page->url, 'type' => 'meta_description', 'value' => 'Explore our services and find the right option.', 'reason' => 'Adds a useful description.']]]])->preventStrayPrompts();
    $created = app(ContentRequestPixelOptimisationGenerator::class)->generate($request, $this->admin);
    expect($created)->toBe($limit)->and(app(ContentBudget::class)->usage($this->plan)['optimisations'])->toBe($limit)
        ->and(app(ContentBudget::class)->usage($this->plan)['copilot'])->toBe(0);
    if ($limit === 0) {
        ContentRequestPixelWriter::assertNeverPrompted();
        expect($request->fresh()->budget_reserved_at)->toBeNull();
    } else {
        ContentRequestPixelWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('services page metadata'));
        expect($request->optimisations()->sole()->status)->toBe(OptimisationStatus::Draft);
    }
})->with([0, 1]);

test('standalone reservations reset at the local month boundary and preserve immutable work type', function (): void {
    $this->plan->update(['timezone' => 'America/Los_Angeles', 'monthly_article_limit' => 1]);
    $this->travelTo(Carbon::parse('2026-11-01 06:30:00', 'UTC'));
    $first = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $second = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $budget = app(ContentBudget::class);
    expect($budget->reserveRequest($first))->toBeNull()->and($budget->reserveRequest($second))->toContain('articles maximum');
    $first->update(['work_type' => 'optimisation']);
    expect($budget->usage($this->plan)['articles'])->toBe(1)->and($budget->usage($this->plan)['optimisations'])->toBe(0);
    $this->travelTo(Carbon::parse('2026-11-01 08:30:00', 'UTC'));
    expect($budget->reserveRequest($second))->toBeNull()->and($budget->usage($this->plan)['articles'])->toBe(1);
});

test('keyword discovery rechecks paid research eligibility and leaves all existing websites opted out by default', function (): void {
    $this->mock(CompetitorResearchAutomation::class)->shouldReceive('allowance')->andReturnNull();
    $this->mock(DataForSEOClient::class)->shouldNotReceive('post');
    expect(app(ContentKeywordResearch::class)->research($this->plan))->toBeFalse()
        ->and((new ContentPlan)->keyword_research_enabled)->toBeFalse()->and($this->plan->fresh()->keyword_researched_at)->toBeNull();
});

test('Copilot defers a request while Pixel owns its preparation lock and cannot fall back to its intended page', function (): void {
    $targetUrl = 'https://example.com/events';
    $this->target->update(['intended_url' => $targetUrl]);
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve '.$targetUrl, 'work_type' => 'optimisation']);
    $lock = Cache::lock('content-request-work-'.$request->id, 180);
    expect($lock->get())->toBeTrue();
    try {
        $selector = app(ContentWorkSelector::class);
        expect($selector->queueStates($this->plan)[$request->id]['state'])->toBe('preparing');
        $work = $selector->select(ContentGeneration::factory()->for($this->plan, 'plan')->make());
        expect($work['requests'])->toHaveCount(0)->and($work['target'])->toBeNull();
    } finally {
        $lock->release();
    }
});

test('manual work reuses an existing impact and completion does not pretend content has been published', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create(['work_type' => 'new_article']);
    $impact = SeoImpact::factory()->for($this->website)->create(['content_request_id' => $request->id]);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $request]))->assertRedirect();
    $this->post(route('admin.content-requests.manual.complete', [$this->website, $request]))->assertRedirect();
    expect($request->fresh()->seoImpact->id)->toBe($impact->id)->and($impact->fresh()->live_at)->toBeNull()
        ->and($request->fresh()->manual_completed_at)->not->toBeNull();
});

test('Copilot usage stays in its reservation month when prompt preparation changes the task start date', function (): void {
    $this->travelTo(Carbon::parse('2026-11-01 00:05:00', 'UTC'));
    $this->plan->update(['timezone' => 'UTC']);
    ContentGeneration::factory()->for($this->plan, 'plan')->create(['budget_reserved_at' => now()->subMinutes(10), 'started_at' => now(),
        'status' => 'failed', 'work_units' => ['articles' => 1, 'optimisations' => 0]]);
    expect(app(ContentBudget::class)->usage($this->plan))->toBe(['articles' => 0, 'optimisations' => 0, 'copilot' => 0]);
    $this->travelTo(now()->subMinutes(10));
    expect(app(ContentBudget::class)->usage($this->plan))->toBe(['articles' => 1, 'optimisations' => 0, 'copilot' => 1]);
});

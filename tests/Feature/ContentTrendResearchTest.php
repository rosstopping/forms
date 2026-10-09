<?php

use App\Jobs\ResearchContentTrends;
use App\Models\ContentPlan;
use App\Models\SeoTargetKeyword;
use App\Models\Website;
use App\Services\CompetitorResearchAutomation;
use App\Services\ContentTrendResearch;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\DataForSEOClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test', 'services.dataforseo.location_code' => 2826]);
    $this->website = Website::factory()->create();
    $this->plan = ContentPlan::factory()->for($this->website)->create(['trend_research_enabled' => true]);
    $this->mock(CompetitorResearchAutomation::class)->shouldReceive('allowance')->andReturn(['interval_days' => 7]);
    foreach (range(1, 7) as $index) {
        SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning '.$index]);
    }
});

test('trend research is opt in bounded and reserved before paid submission without automatic retries', function (): void {
    $provider = $this->mock(DataForSEOClient::class);
    $provider->shouldReceive('post')->once()->withArgs(function ($endpoint, $task, $timeout): bool {
        expect($this->plan->fresh()->trend_research['status'])->toBe('pending');

        return $endpoint === 'keywords_data/dataforseo_trends/explore/live' && count($task['keywords']) === 5 && $task['location_code'] === 2826 && $timeout === 30;
    })->andThrow(new RuntimeException('Timeout with uncertain provider outcome'));
    $service = app(ContentTrendResearch::class);
    expect($service->research($this->plan))->toBeTrue()->and($service->research($this->plan))->toBeFalse()
        ->and($this->plan->fresh()->trend_research['status'])->toBe('failed')->and($this->plan->fresh()->trend_research['provider_cost_usd'])->toBeNull();
    Queue::assertNothingPushed();
});

test('queued trend work rechecks opt in before making any paid request', function (): void {
    $this->plan->update(['trend_research_enabled' => false]);
    $this->mock(DataForSEOClient::class)->shouldNotReceive('post');
    (new ResearchContentTrends($this->plan))->handle(app(ContentTrendResearch::class));
    expect($this->plan->fresh()->trend_researched_at)->toBeNull();
    Http::assertNothingSent();
});

test('trend values map by returned keywords and sparse zero values never mean zero demand', function (): void {
    $provider = $this->mock(DataForSEOClient::class);
    $provider->shouldReceive('post')->once()->andReturnUsing(function ($endpoint, $task): DataForSEOResponse {
        $terms = array_reverse($task['keywords']);
        $points = collect(range(1, 8))->map(fn ($index): array => ['date_from' => now()->subWeeks(10 - $index)->toDateString(), 'date_to' => now()->subWeeks(10 - $index)->addDays(6)->toDateString(),
            'values' => [0, $index > 4 ? 60 : 20, 40, 30, 50]])->all();

        return new DataForSEOResponse($endpoint, [['location_code' => 2826, 'items' => [['type' => 'dataforseo_trends_graph', 'keywords' => $terms, 'data' => $points]]]], .01, 1, 'test-task');
    });
    expect(app(ContentTrendResearch::class)->research($this->plan))->toBeTrue();
    $research = $this->plan->fresh()->trend_research;
    expect($research['provider_cost_usd'])->toBe(.01)->and($research['terms']['event planning 4']['state'])->toBe('rising')
        ->and($research['terms']['event planning 5']['state'])->toBe('insufficient_data')->and($research['terms']['event planning 5']['index_change'])->toBeNull();
});

test('daily discovery dispatches trend research independently of saved opportunity discovery', function (): void {
    $this->plan->update(['discovery_enabled' => false]);
    $this->artisan('content:discover')->assertSuccessful();
    Queue::assertPushed(ResearchContentTrends::class, 1);
    Http::assertNothingSent();
});

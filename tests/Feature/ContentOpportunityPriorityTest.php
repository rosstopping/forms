<?php

use App\Models\ContentPlan;
use App\Models\SeoTargetKeyword;
use App\Models\Website;
use App\Services\ContentOpportunityPriority;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->website = Website::factory()->create();
    $this->plan = ContentPlan::factory()->for($this->website)->create(['content_mode' => 'balanced']);
    $this->action = ['score' => 60, 'url' => 'https://example.com/events', 'queries' => ['event planning'], 'sources' => ['seo'], 'evidence' => [['source' => 'seo', 'checks' => [], 'date' => now(), 'metrics' => ['search_intent' => 'commercial']]]];
});

test('business priority and first party corroboration raise otherwise equal opportunities', function (): void {
    $service = app(ContentOpportunityPriority::class);
    $base = $service->score($this->website, $this->action)['score'];
    SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning', 'priority' => 'high']);
    $this->website->unsetRelation('seoTargetKeywords');
    $aligned = $service->score($this->website, $this->action);
    $corroborated = $service->score($this->website, [...$this->action, 'sources' => ['seo', 'search']]);
    expect($aligned['score'])->toBeGreaterThan($base)->and($corroborated['score'])->toBeGreaterThan($aligned['score'])
        ->and($aligned['priority_reasons'])->toContain('Matches a high-priority business target keyword.');
    Http::assertNothingSent();
});

test('mode mismatch and stale evidence lower priorities but never erase an opportunity', function (): void {
    $this->plan->update(['content_mode' => 'new_only']);
    $score = app(ContentOpportunityPriority::class)->score($this->website, $this->action);
    expect($score['score'])->toBe(35);
    $older = [...$this->action, 'evidence' => [['date' => now()->subDays(31)]]];
    expect(app(ContentOpportunityPriority::class)->score($this->website, $older)['score'])->toBe(25);
});

test('availability and indexing blockers remain above content priority boosts', function (): void {
    $this->plan->update(['content_mode' => 'new_only']);
    $action = [...$this->action, 'evidence' => [['checks' => ['indexable'], 'date' => now()]]];
    expect(app(ContentOpportunityPriority::class)->score($this->website, $action)['score'])->toBe(100);
});

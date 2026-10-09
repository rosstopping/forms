<?php

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\SearchConsoleConnection;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\Website;
use App\Services\SeoTargetKeywordConflicts;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->target = SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning', 'intended_url' => 'https://example.com/events', 'search_intent' => 'commercial']);
    $this->rows = [['query' => 'event planning', 'page' => 'https://example.com/events', 'clicks' => 50, 'impressions' => 1000, 'position' => 3], ['query' => 'event planning', 'page' => 'https://example.com/', 'clicks' => 1, 'impressions' => 10, 'position' => 30]];
});

test('multiple ranking pages alone do not flag cannibalisation and sample positions use impression weights', function (): void {
    $rows = [...$this->rows, ['query' => 'event planning', 'page' => 'https://www.example.com/events/', 'clicks' => 10, 'impressions' => 1000, 'position' => 5]];
    $assessment = app(SeoTargetKeywordConflicts::class)->assess($this->target, $rows);
    expect($assessment['state'])->toBe('multiple_pages')->and($assessment['pages'])->toHaveCount(2)
        ->and($assessment['pages'][0]['position'])->toBe(4.0)->and($assessment['pages'][0]['clicks'])->toEqual(60);
    Http::assertNothingSent();
});

test('substantial visibility on a different destination calls for intent and performance review', function (): void {
    $rows = $this->rows;
    $rows[1]['impressions'] = 1000;
    $assessment = app(SeoTargetKeywordConflicts::class)->assess($this->target, $rows);
    expect($assessment['state'])->toBe('review')->and($assessment['message'])->toContain('commercial intent', 'alone do not establish cannibalisation');
});

test('different desktop ranking URL without matching performance evidence is insufficient for a diagnosis', function (): void {
    SeoTargetKeywordRanking::factory()->for($this->target, 'targetKeyword')->create(['ranking_url' => 'https://example.com/', 'status' => 'ranked']);
    $assessment = app(SeoTargetKeywordConflicts::class)->assess($this->target->fresh(), []);
    expect($assessment['state'])->toBe('review')->and($assessment['message'])->toContain('insufficient');
});

test('saved performance from another property or outside the freshness window is excluded', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $plan = ContentPlan::factory()->for($this->website)->create();
    ContentGeneration::factory()->for($plan, 'plan')->create(['started_at' => now(), 'search_performance_property' => 'sc-domain:old.example', 'search_performance' => $this->rows]);
    $service = app(SeoTargetKeywordConflicts::class);
    expect($service->forTargets($this->website, collect([$this->target]))[$this->target->id]['pages'])->toBeEmpty();
    ContentGeneration::factory()->for($plan, 'plan')->create(['scheduled_for' => now()->subDays(29)->toDateString(), 'started_at' => now()->subDays(29), 'search_performance_property' => 'sc-domain:example.com', 'search_performance' => $this->rows]);
    expect($service->forTargets($this->website, collect([$this->target]))[$this->target->id]['pages'])->toBeEmpty();
    ContentGeneration::factory()->for($plan, 'plan')->create(['scheduled_for' => now()->subDay()->toDateString(), 'started_at' => now(), 'search_performance_property' => 'sc-domain:example.com', 'search_performance' => $this->rows]);
    expect($service->forTargets($this->website, collect([$this->target]))[$this->target->id]['pages'])->toHaveCount(2);
    Http::assertNothingSent();
});

<?php

use App\Models\ExternalApiUsage;
use App\Models\SeoCompetitor;
use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Models\SeoReferringDomain;
use App\Models\SeoSnapshot;
use App\Models\Website;
use Illuminate\Database\QueryException;

test('a website retains historical seo snapshots and resolves the latest observation', function (): void {
    $website = Website::factory()->create();
    $olderSnapshot = SeoSnapshot::factory()->for($website)->create([
        'snapshot_date' => '2026-08-01',
        'organic_keywords' => 126,
        'estimated_organic_traffic' => 640,
    ]);
    $latestSnapshot = SeoSnapshot::factory()->for($website)->create([
        'snapshot_date' => '2026-08-08',
        'organic_keywords' => 139,
        'estimated_organic_traffic' => 720,
    ]);

    expect($website->seoSnapshots()->count())->toBe(2)
        ->and($website->latestSeoSnapshot->is($latestSnapshot))->toBeTrue()
        ->and($olderSnapshot->fresh()->organic_keywords)->toBe(126)
        ->and($latestSnapshot->estimated_organic_traffic)->toBe('720.0000')
        ->and($latestSnapshot->provider)->toBe(SeoSnapshot::PROVIDER_DATAFORSEO);
});

test('snapshot datasets maintain their website and provider boundary', function (): void {
    $snapshot = SeoSnapshot::factory()->create();
    $keyword = SeoKeyword::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create([
        'position' => 12,
        'search_intent' => 'commercial',
    ]);
    $referringDomain = SeoReferringDomain::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create();
    $competitor = SeoCompetitor::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create();
    $opportunity = SeoOpportunity::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->for($keyword, 'keyword')->create();
    $usage = ExternalApiUsage::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create(['cost' => 0.0103]);

    expect($snapshot->keywords()->first()->is($keyword))->toBeTrue()
        ->and($snapshot->referringDomains()->first()->is($referringDomain))->toBeTrue()
        ->and($snapshot->competitors()->first()->is($competitor))->toBeTrue()
        ->and($snapshot->opportunities()->first()->is($opportunity))->toBeTrue()
        ->and($snapshot->apiUsages()->first()->is($usage))->toBeTrue()
        ->and($keyword->snapshot->is($snapshot))->toBeTrue()
        ->and($usage->cost)->toBe('0.010300');
});

test('snapshot scoped duplicate keyword observations are prevented', function (): void {
    $keyword = SeoKeyword::factory()->create();

    expect(fn () => SeoKeyword::factory()
        ->for($keyword->website)
        ->for($keyword->snapshot, 'snapshot')
        ->create(['fingerprint' => $keyword->fingerprint]))
        ->toThrow(QueryException::class);
});

test('deleting a website removes seo intelligence but keeps nullable usage accounting possible', function (): void {
    $snapshot = SeoSnapshot::factory()->create();
    $keyword = SeoKeyword::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create();
    $usage = ExternalApiUsage::factory()->for($snapshot->website)->for($snapshot, 'snapshot')->create();

    $snapshot->website->delete();

    $this->assertModelMissing($snapshot);
    $this->assertModelMissing($keyword);
    expect($usage->fresh())->not->toBeNull()
        ->and($usage->fresh()->website_id)->toBeNull()
        ->and($usage->fresh()->seo_snapshot_id)->toBeNull();
});

<?php

use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use App\Models\SeoProspectSearch;
use App\Models\User;
use App\Services\SeoOpportunityScoringService;

it('scores a suitable SEO prospect from stored ranking audit crawl and migration evidence', function (): void {
    $search = SeoProspectSearch::factory()->create([
        'minimum_position' => 20,
        'maximum_position' => 100,
        'maximum_pages' => 20,
    ]);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create([
        'page_count' => 8,
        'audit_score' => 40,
        'audit_findings' => [
            ['key' => 'meta_description', 'severity' => 'warning', 'message' => 'No meta description was found.', 'source_url' => 'https://example.com'],
        ],
        'migration_difficulty' => 'easy',
        'migration_difficulty_reason' => 'Small brochure website.',
        'observations' => ['indexable_page_count' => 8, 'page_count_band' => 'excellent'],
        'qualification_status' => 'suitable',
    ]);
    $ranking = SeoProspectRanking::factory()->for($candidate, 'candidate')->create([
        'keyword' => 'roofer barnsley',
        'position' => 40,
    ]);

    $result = app(SeoOpportunityScoringService::class)->score($candidate);

    expect($result['opportunity_score'])->toBe(80)
        ->and(data_get($result, 'score_breakdown.ranking.score'))->toBe(30)
        ->and(data_get($result, 'score_breakdown.audit.score'))->toBe(15)
        ->and(data_get($result, 'score_breakdown.siteFit.score'))->toBe(20)
        ->and(data_get($result, 'score_breakdown.migration.score'))->toBe(15)
        ->and(data_get($result, 'observations.outreach.0.evidence.0.id'))->toBe($ranking->id)
        ->and(data_get($result, 'observations.outreach.2.evidence.0.key'))->toBe('meta_description')
        ->and(data_get($result, 'observations.page_count_band'))->toBe('excellent');
});

it('does not score candidates that did not qualify as suitable', function (): void {
    $candidate = SeoProspectCandidate::factory()->create([
        'qualification_status' => 'too_large',
        'opportunity_score' => 75,
        'score_breakdown' => ['stale' => true],
    ]);

    expect(app(SeoOpportunityScoringService::class)->score($candidate))->toBe([
        'opportunity_score' => null,
        'score_breakdown' => null,
    ]);
});

it('filters scored results and orders them by opportunity score by default', function (): void {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $search = SeoProspectSearch::factory()->for($user, 'owner')->create();
    SeoProspectCandidate::factory()->for($search, 'search')->create([
        'domain' => 'lower.example',
        'opportunity_score' => 55,
        'qualification_status' => 'suitable',
        'migration_difficulty' => 'easy',
    ]);
    SeoProspectCandidate::factory()->for($search, 'search')->create([
        'domain' => 'higher.example',
        'opportunity_score' => 85,
        'qualification_status' => 'suitable',
        'migration_difficulty' => 'medium',
    ]);
    SeoProspectCandidate::factory()->for($search, 'search')->create([
        'domain' => 'excluded.example',
        'opportunity_score' => null,
        'qualification_status' => 'too_large',
        'migration_difficulty' => 'medium',
    ]);

    $this->actingAs($user)->get(route('admin.seo-prospect-searches.show', [
        'seoProspectSearch' => $search,
        'qualification' => 'suitable',
        'minimum_score' => 50,
    ]))
        ->assertSuccessful()
        ->assertSeeInOrder(['higher.example', 'lower.example'])
        ->assertDontSee('excluded.example');
});

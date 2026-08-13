<?php

use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Services\SeoIntelligence\SeoOpportunityService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('services.dataforseo.opportunities', [
        'high_volume_minimum' => 100,
        'commercial_volume_minimum' => 20,
        'movement_minimum' => 3,
        'per_type_limit' => 10,
        'maximum_results' => 50,
    ]);
});

test('it turns ranking estimates into actionable opportunity categories', function (): void {
    $keywords = collect([
        seoKeyword(1, 'garden rooms doncaster', 12, 390, 'commercial', 2.75),
        seoKeyword(2, 'garden office ideas', 35, 1000, 'informational'),
        seoKeyword(3, 'tiny query', 45, 10, 'informational'),
    ]);

    $opportunities = collect((new SeoOpportunityService)->find($keywords));
    $gardenRoomTypes = $opportunities
        ->filter(fn (array $opportunity): bool => data_get($opportunity, 'metrics.keyword') === 'garden rooms doncaster')
        ->pluck('type');

    expect($gardenRoomTypes)->toContain(
        SeoOpportunity::TYPE_STRIKING_DISTANCE,
        SeoOpportunity::TYPE_HIGH_VOLUME,
        SeoOpportunity::TYPE_COMMERCIAL,
    )->and($opportunities->where('type', SeoOpportunity::TYPE_HIGH_VOLUME)->pluck('metrics.keyword'))->toContain('garden office ideas')
        ->and($opportunities->pluck('metrics.data_source')->unique()->all())->toBe(['dataforseo_estimate'])
        ->and($opportunities->every(fn (array $opportunity): bool => $opportunity['priority_score'] >= 0 && $opportunity['priority_score'] <= 100))->toBeTrue();
});

test('it compares compatible historical keyword observations for movement', function (): void {
    $current = collect([
        seoKeyword(1, 'declining keyword', 18, 200, 'informational'),
        seoKeyword(2, 'improving keyword', 7, 150, 'commercial'),
        seoKeyword(3, 'stable keyword', 9, 100, 'informational'),
    ]);
    $previous = collect([
        seoKeyword(10, 'Declining Keyword', 10, 200, 'informational'),
        seoKeyword(11, 'improving keyword', 14, 150, 'commercial'),
        seoKeyword(12, 'stable keyword', 10, 100, 'informational'),
    ]);

    $opportunities = collect((new SeoOpportunityService)->find($current, $previous));

    expect($opportunities->where('type', SeoOpportunity::TYPE_DECLINING))->toHaveCount(1)
        ->and($opportunities->firstWhere('type', SeoOpportunity::TYPE_DECLINING)['metrics']['position_change'])->toBe(-8)
        ->and($opportunities->where('type', SeoOpportunity::TYPE_IMPROVING))->toHaveCount(1)
        ->and($opportunities->firstWhere('type', SeoOpportunity::TYPE_IMPROVING)['metrics']['position_change'])->toBe(7)
        ->and($opportunities->whereIn('type', [SeoOpportunity::TYPE_DECLINING, SeoOpportunity::TYPE_IMPROVING])->pluck('metrics.keyword'))->not->toContain('stable keyword');
});

test('it applies per-type and overall cost-free result limits', function (): void {
    config()->set('services.dataforseo.opportunities.per_type_limit', 2);
    config()->set('services.dataforseo.opportunities.maximum_results', 3);
    $keywords = collect(range(1, 8))->map(fn (int $id): SeoKeyword => seoKeyword($id, 'commercial keyword '.$id, 12, 100 + $id, 'commercial', 1.5));

    $opportunities = (new SeoOpportunityService)->find($keywords);

    expect($opportunities)->toHaveCount(3)
        ->and(collect($opportunities)->countBy('type')->max())->toBeLessThanOrEqual(2);
});

function seoKeyword(int $id, string $keyword, int $position, int $volume, string $intent, ?float $cpc = null): SeoKeyword
{
    $model = new SeoKeyword([
        'fingerprint' => hash('sha256', mb_strtolower($keyword)),
        'keyword' => $keyword,
        'position' => $position,
        'ranking_url' => 'https://example.com/'.str_replace(' ', '-', mb_strtolower($keyword)),
        'search_volume' => $volume,
        'cpc' => $cpc,
        'search_intent' => $intent,
        'estimated_traffic' => 10,
        'location_code' => 2826,
        'language_code' => 'en',
    ]);
    $model->id = $id;

    return $model;
}

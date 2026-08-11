<?php

use App\Services\SearchOpportunityFinder;

test('it finds actionable opportunities from comparable search periods', function () {
    $finder = new SearchOpportunityFinder(100, 50, 0.02, 0.30, 5);
    $current = [
        ['query' => 'villa holiday', 'page' => 'https://example.com/villas', 'clicks' => 7.0, 'impressions' => 500.0, 'ctr' => 0.014, 'position' => 7.0],
        ['query' => 'declining guide', 'page' => 'https://example.com/guide', 'clicks' => 5.0, 'impressions' => 200.0, 'ctr' => 0.025, 'position' => 12.0],
        ['query' => 'new local query', 'page' => 'https://example.com/local', 'clicks' => 2.0, 'impressions' => 80.0, 'ctr' => 0.025, 'position' => 18.0],
        ['query' => 'shared query', 'page' => 'https://example.com/one', 'clicks' => 4.0, 'impressions' => 80.0, 'ctr' => 0.05, 'position' => 5.0],
        ['query' => 'shared query', 'page' => 'https://example.com/two', 'clicks' => 2.0, 'impressions' => 60.0, 'ctr' => 0.033, 'position' => 9.0],
    ];
    $previous = [
        ['query' => 'villa holiday', 'page' => 'https://example.com/villas', 'clicks' => 8.0, 'impressions' => 450.0, 'ctr' => 0.018, 'position' => 8.0],
        ['query' => 'declining guide', 'page' => 'https://example.com/guide', 'clicks' => 20.0, 'impressions' => 300.0, 'ctr' => 0.067, 'position' => 6.0],
    ];

    $opportunities = collect($finder->find($current, $previous));

    expect($opportunities->pluck('type')->unique()->all())
        ->toContain('ranking_gap', 'low_ctr', 'declining', 'emerging', 'cannibalisation')
        ->and($opportunities->every(fn (array $item): bool => strlen($item['fingerprint']) === 64))->toBeTrue();
});

test('it enforces the per-type limit by priority', function () {
    $finder = new SearchOpportunityFinder(100, 50, 0.02, 0.30, 2);
    $rows = collect(range(1, 4))->map(fn (int $index): array => [
        'query' => 'query '.$index,
        'page' => 'https://example.com/'.$index,
        'clicks' => 2.0,
        'impressions' => (float) (100 * $index),
        'ctr' => 0.03,
        'position' => 10.0,
    ])->all();

    $rankingGaps = collect($finder->find($rows, []))->where('type', 'ranking_gap');

    expect($rankingGaps)->toHaveCount(2)
        ->and($rankingGaps->pluck('query')->all())->toBe(['query 4', 'query 3']);
});

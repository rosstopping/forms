<?php

use App\Services\DataForSEO\Data\DomainOverviewData;
use App\Services\DataForSEO\Data\RankedKeywordData;

test('it maps organic domain metrics into cumulative position groups', function (): void {
    $overview = DomainOverviewData::fromResults([['items' => [['metrics' => ['organic' => [
        'pos_1' => 4,
        'pos_2_3' => 6,
        'pos_4_10' => 8,
        'pos_11_20' => 12,
        'pos_21_30' => 7,
        'pos_31_40' => 5,
        'pos_41_50' => 4,
        'pos_51_60' => 3,
        'pos_61_70' => 2,
        'pos_71_80' => 2,
        'pos_81_90' => 1,
        'pos_91_100' => 1,
        'count' => 55,
        'etv' => 720.25,
    ]]]]]]);

    expect($overview->organicKeywords)->toBe(55)
        ->and($overview->estimatedOrganicTraffic)->toBe(720.25)
        ->and($overview->top3Keywords)->toBe(10)
        ->and($overview->top10Keywords)->toBe(18)
        ->and($overview->top20Keywords)->toBe(30)
        ->and($overview->top100Keywords)->toBe(55);
});

test('it maps only fields supplied by a valid ranked keyword item', function (): void {
    $keyword = RankedKeywordData::fromArray([
        'keyword_data' => [
            'keyword' => 'Garden Rooms Doncaster',
            'location_code' => 2826,
            'language_code' => 'en',
            'keyword_info' => ['search_volume' => 390, 'cpc' => 2.75, 'competition' => 0.42, 'competition_level' => 'MEDIUM'],
            'keyword_properties' => ['keyword_difficulty' => 31],
            'search_intent_info' => ['main_intent' => 'commercial'],
        ],
        'ranked_serp_element' => ['serp_item' => [
            'rank_absolute' => 12,
            'url' => 'https://example.com/garden-rooms',
            'etv' => 18.4,
            'rank_changes' => ['previous_rank_absolute' => 15],
        ]],
    ]);

    expect($keyword)->not->toBeNull()
        ->and($keyword->keyword)->toBe('Garden Rooms Doncaster')
        ->and($keyword->position)->toBe(12)
        ->and($keyword->previousPosition)->toBe(15)
        ->and($keyword->searchVolume)->toBe(390)
        ->and($keyword->cpc)->toBe(2.75)
        ->and($keyword->competition)->toBe(0.42)
        ->and($keyword->searchIntent)->toBe('commercial')
        ->and($keyword->estimatedTraffic)->toBe(18.4)
        ->and($keyword->keywordDifficulty)->toBe(31);
});

test('it rejects incomplete ranked keyword items', function (array $item): void {
    expect(RankedKeywordData::fromArray($item))->toBeNull();
})->with([
    'missing keyword' => [['keyword_data' => ['location_code' => 2826, 'language_code' => 'en'], 'ranked_serp_element' => ['serp_item' => ['rank_absolute' => 1]]]],
    'missing position' => [['keyword_data' => ['keyword' => 'garden room', 'location_code' => 2826, 'language_code' => 'en']]],
]);

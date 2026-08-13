<?php

namespace App\Services\DataForSEO\Data;

use Illuminate\Support\Str;

final readonly class RankedKeywordData
{
    public function __construct(
        public string $fingerprint,
        public string $keyword,
        public int $position,
        public ?int $previousPosition,
        public ?string $rankingUrl,
        public ?int $searchVolume,
        public ?float $cpc,
        public ?float $competition,
        public ?string $competitionLevel,
        public ?string $searchIntent,
        public ?float $estimatedTraffic,
        public ?int $keywordDifficulty,
        public int $locationCode,
        public string $languageCode,
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromArray(array $item): ?self
    {
        $keyword = data_get($item, 'keyword_data.keyword');
        $position = data_get($item, 'ranked_serp_element.serp_item.rank_group');
        $locationCode = data_get($item, 'keyword_data.location_code');
        $languageCode = data_get($item, 'keyword_data.language_code');

        if (! is_string($keyword) || trim($keyword) === '' || ! is_numeric($position) || ! is_numeric($locationCode) || ! is_string($languageCode) || $languageCode === '') {
            return null;
        }

        $rankingUrl = self::nullableString(data_get($item, 'ranked_serp_element.serp_item.url'));

        return new self(
            fingerprint: hash('sha256', Str::lower(trim($keyword)).'|'.Str::lower($rankingUrl ?? '')),
            keyword: trim($keyword),
            position: (int) $position,
            previousPosition: self::nullableInt(data_get($item, 'ranked_serp_element.serp_item.rank_changes.previous_rank_group')),
            rankingUrl: $rankingUrl,
            searchVolume: self::nullableInt(data_get($item, 'keyword_data.keyword_info.search_volume')),
            cpc: self::nullableFloat(data_get($item, 'keyword_data.keyword_info.cpc')),
            competition: self::nullableFloat(data_get($item, 'keyword_data.keyword_info.competition')),
            competitionLevel: self::nullableString(data_get($item, 'keyword_data.keyword_info.competition_level')),
            searchIntent: self::nullableString(data_get($item, 'keyword_data.search_intent_info.main_intent')),
            estimatedTraffic: self::nullableFloat(data_get($item, 'ranked_serp_element.serp_item.etv')),
            keywordDifficulty: self::nullableInt(data_get($item, 'keyword_data.keyword_properties.keyword_difficulty')),
            locationCode: (int) $locationCode,
            languageCode: $languageCode,
        );
    }

    protected static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

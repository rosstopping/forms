<?php

namespace App\Services\DataForSEO\Data;

final readonly class DomainOverviewData
{
    public function __construct(
        public int $organicKeywords,
        public float $estimatedOrganicTraffic,
        public int $top3Keywords,
        public int $top10Keywords,
        public int $top20Keywords,
        public int $top100Keywords,
    ) {}

    /** @param array<int, array<string, mixed>> $results */
    public static function fromResults(array $results): self
    {
        $organic = data_get($results, '0.items.0.metrics.organic', []);
        $organic = is_array($organic) ? $organic : [];
        $position = fn (string $key): int => is_numeric($organic[$key] ?? null) ? (int) $organic[$key] : 0;
        $top3 = $position('pos_1') + $position('pos_2_3');
        $top10 = $top3 + $position('pos_4_10');
        $top20 = $top10 + $position('pos_11_20');
        $top100 = $top20 + collect([
            'pos_21_30', 'pos_31_40', 'pos_41_50', 'pos_51_60',
            'pos_61_70', 'pos_71_80', 'pos_81_90', 'pos_91_100',
        ])->sum(fn (string $key): int => $position($key));

        return new self(
            organicKeywords: is_numeric($organic['count'] ?? null) ? (int) $organic['count'] : $top100,
            estimatedOrganicTraffic: is_numeric($organic['etv'] ?? null) ? (float) $organic['etv'] : 0,
            top3Keywords: $top3,
            top10Keywords: $top10,
            top20Keywords: $top20,
            top100Keywords: $top100,
        );
    }
}

<?php

namespace App\Services\DataForSEO\Data;

final readonly class RankedKeywordsResponse
{
    /** @param array<int, RankedKeywordData> $keywords */
    public function __construct(
        public array $keywords,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
        public string $endpoint,
    ) {}
}

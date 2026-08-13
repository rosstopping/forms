<?php

namespace App\Services\DataForSEO\Data;

final readonly class OrganicCompetitorsResponse
{
    /** @param array<int, OrganicCompetitorData> $competitors */
    public function __construct(
        public array $competitors,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
        public string $endpoint,
    ) {}
}

<?php

namespace App\Services\DataForSEO\Data;

final readonly class DomainOverviewResponse
{
    public function __construct(
        public DomainOverviewData $overview,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
        public string $endpoint,
    ) {}
}

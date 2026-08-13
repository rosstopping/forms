<?php

namespace App\Services\DataForSEO\Data;

final readonly class BacklinkOverviewResponse
{
    public function __construct(
        public BacklinkOverviewData $overview,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
        public string $endpoint,
    ) {}
}

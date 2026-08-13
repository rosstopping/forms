<?php

namespace App\Services\DataForSEO\Data;

final readonly class ReferringDomainsResponse
{
    /** @param array<int, ReferringDomainData> $domains */
    public function __construct(
        public array $domains,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
        public string $endpoint,
    ) {}
}

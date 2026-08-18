<?php

namespace App\Data;

use Illuminate\Support\Collection;

final readonly class SerpSearchResponse
{
    /** @param Collection<int, SerpResult> $results */
    public function __construct(
        public string $provider,
        public string $endpoint,
        public Collection $results,
        public float $cost = 0,
        public ?string $taskId = null,
        public bool $cached = false,
        public ?string $fetchedAt = null,
    ) {}
}

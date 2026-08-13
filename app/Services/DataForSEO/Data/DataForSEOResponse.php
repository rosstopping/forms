<?php

namespace App\Services\DataForSEO\Data;

final readonly class DataForSEOResponse
{
    /** @param array<int, array<string, mixed>> $results */
    public function __construct(
        public string $endpoint,
        public array $results,
        public float $cost,
        public int $resultCount,
        public ?string $taskId,
    ) {}
}

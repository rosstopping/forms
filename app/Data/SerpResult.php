<?php

namespace App\Data;

final readonly class SerpResult
{
    public function __construct(
        public int $position,
        public string $url,
        public string $domain,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $websiteName = null,
    ) {}
}

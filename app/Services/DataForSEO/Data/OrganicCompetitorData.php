<?php

namespace App\Services\DataForSEO\Data;

use Illuminate\Support\Str;

final readonly class OrganicCompetitorData
{
    public function __construct(
        public string $domain,
        public int $commonKeywords,
        public ?int $organicKeywords,
        public ?float $estimatedTraffic,
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromArray(array $item): ?self
    {
        $domain = data_get($item, 'domain');

        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        return new self(
            domain: Str::lower(trim($domain)),
            commonKeywords: self::integer(data_get($item, 'intersections')),
            organicKeywords: self::nullableInteger(data_get($item, 'full_domain_metrics.organic.count')),
            estimatedTraffic: self::nullableFloat(data_get($item, 'full_domain_metrics.organic.etv')),
        );
    }

    protected static function integer(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    protected static function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    protected static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? max(0, (float) $value) : null;
    }
}

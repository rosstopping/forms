<?php

namespace App\Services\DataForSEO\Data;

final readonly class BacklinkOverviewData
{
    public function __construct(
        public int $backlinks,
        public int $referringDomains,
        public int $referringIps,
        public int $referringSubnets,
        public int $brokenBacklinks,
        public ?int $domainRank,
    ) {}

    /** @param array<int, array<string, mixed>> $results */
    public static function fromResults(array $results): self
    {
        $result = data_get($results, '0', []);

        return new self(
            backlinks: self::integer(data_get($result, 'backlinks')),
            referringDomains: self::integer(data_get($result, 'referring_domains')),
            referringIps: self::integer(data_get($result, 'referring_ips')),
            referringSubnets: self::integer(data_get($result, 'referring_subnets')),
            brokenBacklinks: self::integer(data_get($result, 'broken_backlinks')),
            domainRank: self::nullableInteger(data_get($result, 'rank')),
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
}

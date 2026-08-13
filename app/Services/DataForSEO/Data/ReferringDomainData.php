<?php

namespace App\Services\DataForSEO\Data;

use Illuminate\Support\Carbon;
use Throwable;

final readonly class ReferringDomainData
{
    public function __construct(
        public string $domain,
        public ?int $domainRank,
        public int $backlinksCount,
        public ?Carbon $firstSeen,
        public ?Carbon $lastSeen,
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromArray(array $item): ?self
    {
        $domain = data_get($item, 'domain');

        if (! is_string($domain) || trim($domain) === '') {
            return null;
        }

        return new self(
            domain: mb_strtolower(trim($domain)),
            domainRank: self::nullableInteger(data_get($item, 'rank')),
            backlinksCount: self::integer(data_get($item, 'backlinks')),
            firstSeen: self::date(data_get($item, 'first_seen')),
            lastSeen: self::date(data_get($item, 'last_seen')),
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

    protected static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}

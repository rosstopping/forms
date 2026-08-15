<?php

namespace App\Support;

class MembershipPlan
{
    public const ESSENTIAL = 'essential';

    public const GROWTH = 'growth';

    public const COMPLETE = 'complete';

    public const FEATURE_GROWTH = 'growth';

    public const FEATURE_COMPLETE = 'complete';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return config('memberships.plans', []);
    }

    /** @return array<string, mixed>|null */
    public static function find(string $tier): ?array
    {
        return self::all()[$tier] ?? null;
    }

    public static function tierForPrice(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        foreach (self::all() as $tier => $plan) {
            if (($plan['stripe_price_id'] ?? null) === $priceId) {
                return $tier;
            }
        }

        return null;
    }

    public static function includes(?string $tier, string $feature): bool
    {
        $ranks = [self::ESSENTIAL => 1, self::GROWTH => 2, self::COMPLETE => 3];
        $requiredRank = match ($feature) {
            self::FEATURE_GROWTH => 2,
            self::FEATURE_COMPLETE => 3,
            default => 1,
        };

        return ($ranks[$tier] ?? 0) >= $requiredRank;
    }
}

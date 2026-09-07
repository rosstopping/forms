<?php

namespace App\Support;

use Carbon\CarbonImmutable;

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
            if (($plan['stripe_price_id'] ?? null) === $priceId
                || ($tier === self::GROWTH && config('memberships.growth_offer.stripe_price_id') === $priceId)) {
                return $tier;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function activeGrowthOffer(): ?array
    {
        $offer = config('memberships.growth_offer');

        if (! is_array($offer)) {
            return null;
        }

        $now = CarbonImmutable::now(config('app.timezone'));
        $startsAt = CarbonImmutable::parse((string) $offer['starts_at'], config('app.timezone'));
        $endsAt = CarbonImmutable::parse((string) $offer['ends_at'], config('app.timezone'));

        return $now->betweenIncluded($startsAt, $endsAt) ? $offer : null;
    }

    public static function checkoutPriceId(string $tier): string
    {
        if ($tier === self::GROWTH && self::activeGrowthOffer()) {
            return (string) config('memberships.growth_offer.stripe_price_id');
        }

        return (string) (self::find($tier)['stripe_price_id'] ?? '');
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

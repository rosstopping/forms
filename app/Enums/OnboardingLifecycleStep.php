<?php

namespace App\Enums;

enum OnboardingLifecycleStep: string
{
    case Welcome = 'welcome';
    case SearchConsole = 'search_console';
    case BookCall = 'book_call';
    case Progress = 'progress';
    case GrowthFeatures = 'growth_features';
    case EndingSoon = 'ending_soon';
    case TrialEnded = 'trial_ended';
    case AdminFollowUp = 'admin_follow_up';

    public function day(): int
    {
        return match ($this) {
            self::Welcome => 0,
            self::SearchConsole => 2,
            self::BookCall => 4,
            self::Progress => 7,
            self::GrowthFeatures => 10,
            self::EndingSoon => 13,
            self::TrialEnded => 14,
            self::AdminFollowUp => 17,
        };
    }

    public function audience(): string
    {
        return $this === self::AdminFollowUp ? 'admin' : 'customer';
    }

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Trial welcome',
            self::SearchConsole => 'Search Console reminder',
            self::BookCall => 'Call reminder',
            self::Progress => 'Progress summary',
            self::GrowthFeatures => 'Growth features',
            self::EndingSoon => 'Trial ending reminder',
            self::TrialEnded => 'Trial ended',
            self::AdminFollowUp => 'Admin follow-up',
        };
    }
}

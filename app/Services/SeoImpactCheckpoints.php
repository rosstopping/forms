<?php

namespace App\Services;

use App\Models\SeoImpact;

class SeoImpactCheckpoints
{
    public const CONTENT_DAYS = [14, 30, 60, 90];

    /** @return list<int> */
    public function days(SeoImpact $impact): array
    {
        return $impact->measurement_checkpoints ?: [28, 56];
    }

    public function next(SeoImpact $impact): ?int
    {
        foreach ($this->days($impact) as $day) {
            if ($day > $impact->review_after_days) {
                return $day;
            }
        }

        return null;
    }

    public function windowDays(SeoImpact $impact): int
    {
        return min(28, $impact->review_after_days);
    }
}

<?php

namespace App\Services;

class SeoImpactEvaluator
{
    /** @return array{outcome: string, reason: string, change: ?float, percent: ?float} */
    public function assess(array $baseline, array $measurement, string $metric, bool $confounded = false): array
    {
        $before = data_get($baseline, 'target.totals');
        $after = data_get($measurement, 'target.totals');
        $result = ['outcome' => 'insufficient_data', 'reason' => 'Not enough comparable Search Console evidence yet.', 'change' => null, 'percent' => null];
        if (! data_get($baseline, 'complete') || ! data_get($measurement, 'complete') || ! $before || ! $after
            || min($before['reported_days'], $after['reported_days']) < 7
            || min($before['impressions'], $after['impressions']) < ($metric === 'ctr' ? 500 : 100)
            || $before['clicks'] < 10 || $before['clicks'] + $after['clicks'] < 20) {
            return $result;
        }
        $change = $after[$metric] - $before[$metric];
        $percent = $before[$metric] > 0 ? $change / $before[$metric] * 100 : null;
        $result = [...$result, 'outcome' => 'inconclusive', 'change' => round($change, 4), 'percent' => $percent === null ? null : round($percent, 1)];
        if ($confounded) {
            return [...$result, 'reason' => 'Another tracked change overlaps these pages or search terms. Review the changes together.'];
        }
        if ($metric === 'ctr' && abs($after['position'] - $before['position']) > 1) {
            return [...$result, 'reason' => 'Average position also changed by more than one place, so the CTR comparison is inconclusive.'];
        }
        if ($percent === null || abs($percent) < 20 || ($metric === 'clicks' && abs($change) < 5)) {
            return [...$result, 'reason' => 'Movement is below the review threshold (20%, and at least five clicks for click-led work).'];
        }
        $controlBefore = data_get($baseline, 'control.totals.clicks');
        $controlAfter = data_get($measurement, 'control.totals.clicks');
        if (data_get($baseline, 'control.complete') && data_get($measurement, 'control.complete') && $controlBefore >= 10 && $controlAfter !== null) {
            $controlPercent = ($controlAfter - $controlBefore) / $controlBefore * 100;
            if (($controlPercent > 0) === ($percent > 0) && abs($controlPercent) >= abs($percent) * 0.75 && $metric === 'clicks') {
                return [...$result, 'reason' => 'The comparison page moved similarly. A wider demand or site trend may explain the change.'];
            }
        }

        return [...$result, 'outcome' => $change > 0 ? 'improved' : 'declined', 'reason' => 'Observed movement passed the review threshold. This is directional evidence, not statistical significance or proof that the edit caused it.'];
    }
}

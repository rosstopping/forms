<?php

namespace App\Services;

use App\Models\SearchConsoleDailyMetric;
use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SearchConsoleProgress
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function forWebsite(Website $website, array $input = []): array
    {
        $connection = $website->searchConsoleConnection()->first();
        $query = SearchConsoleDailyMetric::where('website_id', $website->id)
            ->where('search_console_connection_id', $connection?->id)
            ->where('property_hash', hash('sha256', $connection?->property_url ?? ''));
        $last = (clone $query)->whereDate('date', '<=', ReportingPeriod::cutoff()->toDateString())->max('date');
        $period = ReportingPeriod::fromInput($input, $last ? CarbonImmutable::parse($last, 'America/Los_Angeles') : null);
        $rows = $query->whereDate('date', '>=', $period->previousStart->min($period->start)->toDateString())->whereDate('date', '<=', $period->end->toDateString())->get();
        $current = $this->totals($rows, $period->start, $period->end);
        $previous = $this->totals($rows, $period->previousStart, $period->previousEnd);
        $change = $current['complete'] && $previous['complete'] && $previous['clicks'] > 0
            ? round(($current['clicks'] - $previous['clicks']) / $previous['clicks'] * 100, 1) : null;

        return ['period' => $period, 'current' => $current, 'previous' => $previous, 'click_change' => $change, 'last_date' => $last ? CarbonImmutable::parse($last)->toDateString() : null,
            'access_denied' => $connection?->access_denied_at !== null];
    }

    /** @param Collection<int, SearchConsoleDailyMetric> $rows
     * @return array<string, mixed>
     */
    private function totals(Collection $rows, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $observed = $rows->filter(fn ($row): bool => $row->data_status === 'observed' && $row->date->toDateString() >= $start->toDateString() && $row->date->toDateString() <= $end->toDateString());
        $days = (int) $start->diffInDays($end) + 1;
        $clicks = $observed->sum('clicks');
        $impressions = $observed->sum('impressions');

        return ['complete' => $observed->count() === $days, 'days' => $days, 'reported_days' => $observed->count(),
            'clicks' => $observed->isEmpty() ? null : $clicks, 'impressions' => $observed->isEmpty() ? null : $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions : null,
            'position' => $impressions > 0 ? $observed->sum(fn ($row): float => $row->position * $row->impressions) / $impressions : null];
    }
}

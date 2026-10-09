<?php

namespace App\Services;

use App\Models\SearchConsoleDailyMetric;
use App\Models\SeoImpact;
use App\Models\SeoWin;
use App\Models\Website;
use Carbon\CarbonImmutable;

class SeoPerformanceSignals
{
    public function __construct(private SearchConsoleProgress $progress) {}

    public function detect(Website $website): void
    {
        $connection = $website->searchConsoleConnection;
        if (! $website->is_active || ! $connection?->property_url || $connection->access_denied_at) {
            return;
        }
        $this->traffic($website, $connection->property_url);
        $this->content($website, $connection->property_url);
    }

    private function traffic(Website $website, string $property): void
    {
        $report = $this->progress->forWebsite($website);
        $end = $report['period']->end;
        $connection = $website->searchConsoleConnection;
        $rows = SearchConsoleDailyMetric::where('website_id', $website->id)->where('search_console_connection_id', $connection->id)
            ->where('property_hash', hash('sha256', $property))->whereDate('date', '>=', $end->subYearNoOverflow()->subDays(30)->toDateString())
            ->whereDate('date', '<=', $end->toDateString())->get();
        $reports = [];
        foreach (['previous', 'year'] as $comparison) {
            foreach ([0, 3] as $offset) {
                $day = $end->subDays($offset);
                $period = ReportingPeriod::fromInput(['period' => 'custom', 'comparison' => $comparison,
                    'start' => $day->subDays(27)->toDateString(), 'end' => $day->toDateString()]);
                $current = $this->progress->totals($rows, $period->start, $period->end);
                $previous = $this->progress->totals($rows, $period->previousStart, $period->previousEnd);
                $reports[$comparison][$offset] = ['period' => $period, 'current' => $current, 'previous' => $previous,
                    'click_change' => $current['complete'] && $previous['complete'] && $previous['clicks'] > 0
                        ? round(($current['clicks'] - $previous['clicks']) / $previous['clicks'] * 100, 1) : null];
            }
        }
        foreach (['previous', 'year'] as $comparison) {
            $current = $reports[$comparison][0];
            $first = $reports[$comparison][3];
            if (! $current['current']['complete'] || ! $current['previous']['complete'] || ! $first['current']['complete'] || ! $first['previous']['complete']) {
                continue;
            }
            $changes = [$current['click_change'], $first['click_change']];
            $deltas = [$current['current']['clicks'] - $current['previous']['clicks'], $first['current']['clicks'] - $first['previous']['clicks']];
            $rule = null;
            $category = 'win';
            if (min($changes) >= 25 && min($deltas) >= 50 && ! in_array(null, $changes, true)) {
                $rule = $comparison === 'year' ? 'traffic_year_growth' : 'traffic_growth';
            } elseif ($comparison === 'previous' && max($changes) <= -30 && max($deltas) <= -50 && ! in_array(null, $changes, true)) {
                $rule = 'traffic_decline';
                $category = 'alert';
            }
            if ($rule) {
                $title = match ($rule) {
                    'traffic_year_growth' => 'Search clicks increased year on year',
                    'traffic_decline' => 'Sustained decline in search clicks',
                    default => 'Search clicks increased over the last 28 days',
                };
                $evidence = $this->trafficEvidence($current, $first, $property, $comparison);
                $year = $reports['year'][0];
                if ($comparison === 'previous' && $year['current']['complete'] && $year['previous']['complete']) {
                    $evidence['year_comparison'] = ['start' => $year['period']->previousStart->toDateString(), 'end' => $year['period']->previousEnd->toDateString(),
                        'clicks' => $year['previous']['clicks'], 'percent' => $year['click_change']];
                }
                $text = 'Quick SEO update — your website received '.$current['current']['clicks'].' Google Search clicks in the 28 days ending '.$end->format('j M Y').', compared with '.$current['previous']['clicks'].' in the '.($comparison === 'year' ? 'equivalent period last year' : 'previous 28 days').'. That’s '.abs($current['click_change']).'% '.($category === 'alert' ? 'lower' : 'higher').'. The pattern also appears in a window ending three days earlier. Seasonal demand and other changes may contribute; this does not establish what caused it.';
                $this->save($website, $rule, $category, $title, $evidence, $text, $end, $property.':'.$end->format('Y-m'), true);
            }
        }
        $current = $reports['previous'][0];
        $first = $reports['previous'][3];
        if ($current['current']['complete'] && $current['previous']['complete'] && $first['current']['complete'] && $first['previous']['complete']) {
            $thresholds = array_filter([100, 500, 1000, 5000, 10000], fn (int $threshold): bool => min($current['current']['clicks'], $first['current']['clicks']) >= $threshold
                && max($current['previous']['clicks'], $first['previous']['clicks']) < $threshold);
            if ($thresholds !== []) {
                $threshold = max($thresholds);
                $this->save($website, 'traffic_milestone', 'win', 'Passed '.$threshold.' Google Search clicks in 28 days',
                    [...$this->trafficEvidence($current, $first, $property, 'previous'), 'threshold' => $threshold],
                    'A nice milestone — your website passed '.$threshold.' clicks from Google Search in a rolling 28-day period, confirmed in two windows ending three days apart. These are search clicks, rather than visits or enquiries.',
                    $end, $property.':'.$threshold);
            }
        }
    }

    /** @return array<string, mixed> */
    private function trafficEvidence(array $current, array $first, string $property, string $comparison): array
    {
        return ['source' => 'search_console_daily', 'property_url' => $property, 'comparison' => $comparison,
            'start' => $current['period']->start->toDateString(), 'end' => $current['period']->end->toDateString(),
            'previous_start' => $current['period']->previousStart->toDateString(), 'previous_end' => $current['period']->previousEnd->toDateString(),
            'current' => $current['current'], 'previous' => $current['previous'], 'percent' => $current['click_change'],
            'confirmation' => ['end' => $first['period']->end->toDateString(), 'current' => $first['current'], 'previous' => $first['previous'], 'percent' => $first['click_change']],
            'seasonality' => 'Equivalent windows do not establish causation; consider seasonal demand and business changes.'];
    }

    private function content(Website $website, string $property): void
    {
        SeoImpact::where('website_id', $website->id)->where('property_url', $property)->whereNotNull('live_at')
            ->whereNotNull('verified_at')->where('verified_at', '<=', now())->whereIn('verification_status', ['passed', 'checked'])->whereNotNull('content_request_id')
            ->where('live_at', '>=', now()->subDays(120))->with(['reviews', 'contentRequest'])->each(function (SeoImpact $impact) use ($website, $property): void {
                if ($impact->target_urls === [] || $impact->contentRequest?->website_id !== $website->id) {
                    return;
                }
                foreach ($impact->reviews as $review) {
                    $measurement = $review->measurement;
                    $baseline = $review->baseline;
                    if (! data_get($measurement, 'complete') || data_get($measurement, 'source') !== 'search_console'
                        || $review->period_end->toDateString() > ReportingPeriod::cutoff()->toDateString()
                        || $review->period_end->lessThan(now()->subDays(35))
                        || $review->period_start->toDateString() <= $impact->live_at->copy()->setTimezone('America/Los_Angeles')->toDateString()) {
                        continue;
                    }
                    $after = data_get($measurement, 'target.totals', []);
                    $before = data_get($baseline, 'target.totals', []);
                    if (($after['reported_days'] ?? 0) < 7 || ($after['impressions'] ?? 0) < 100 || ($after['clicks'] ?? 0) < 5) {
                        continue;
                    }
                    $isNew = $impact->contentRequest->work_type === 'new_article' || $impact->contentRequest->work_type === 'new_page';
                    $growth = $review->outcome === 'improved' && data_get($baseline, 'complete')
                        && ($baseline['window_days'] ?? null) === ($measurement['window_days'] ?? null)
                        && ($before['clicks'] ?? 0) > 0 && ($before['reported_days'] ?? 0) >= 7
                        && ($after['clicks'] ?? 0) >= ($before['clicks'] ?? 0) * 1.25
                        && ($after['clicks'] ?? 0) - ($before['clicks'] ?? 0) >= 10;
                    if (! $isNew && ! $growth) {
                        continue;
                    }
                    $rule = $isNew ? 'content_traction' : 'content_growth';
                    $title = ($isNew ? 'Published content is gaining search clicks' : 'Updated content has improved search clicks').' · '.$impact->title;
                    $evidence = ['source' => 'search_console_content_review', 'property_url' => $property, 'impact_id' => $impact->id,
                        'content_request_id' => $impact->content_request_id, 'content_generation_id' => $impact->content_generation_id,
                        'review_id' => $review->id, 'checkpoint' => $review->checkpoint, 'urls' => $impact->target_urls, 'keywords' => $impact->target_queries,
                        'live_at' => $impact->live_at->toIso8601String(), 'verified_at' => $impact->verified_at->toIso8601String(),
                        'start' => $review->period_start->toDateString(), 'end' => $review->period_end->toDateString(), 'current' => $after, 'previous' => $before,
                        'assessment' => $review->assessment, 'causation' => 'Observed after publication; the work has not been established as the cause.'];
                    $this->save($website, $rule, 'win', $title, $evidence,
                        'Quick content update — the tracked '.(count($impact->target_urls) === 1 ? 'page has' : 'pages have').' received '.$after['clicks'].' Google Search clicks and '.$after['impressions'].' impressions between '.$review->period_start->format('j M').' and '.$review->period_end->format('j M Y').', following publication or changes on '.$impact->live_at->format('j M Y').'. A useful sign of search visibility; this does not prove the work caused the result.',
                        CarbonImmutable::instance($review->period_end), (string) $impact->id, false, $impact->id);
                    break;
                }
            });
    }

    private function save(Website $website, string $rule, string $category, string $title, array $evidence, string $draft, CarbonImmutable $end, string $key, bool $cooldown = false, ?int $impactId = null): void
    {
        if ($cooldown && SeoWin::where('website_id', $website->id)->where('rule', $rule)->where('confirmed_at', '>=', $end->subDays(90))
            ->where('evidence->property_url', $evidence['property_url'])->exists()) {
            return;
        }
        SeoWin::firstOrCreate(['website_id' => $website->id, 'fingerprint' => hash('sha256', json_encode([$category, $rule, $key]))], [
            'category' => $category, 'seo_impact_id' => $impactId, 'rule' => $rule, 'rule_version' => 1,
            'importance' => $category === 'alert' ? 'high' : 'normal', 'confidence' => 'medium', 'title' => mb_substr($title, 0, 255),
            'evidence' => $evidence, 'observed_at' => ($impactId === null ? $end->subDays(3) : $end)->utc(), 'confirmed_at' => $end->utc(), 'client_draft' => $draft,
        ]);
    }
}

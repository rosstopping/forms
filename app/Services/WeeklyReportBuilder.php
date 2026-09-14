<?php

namespace App\Services;

use App\Models\BusinessProfilePost;
use App\Models\BusinessProfileRecommendation;
use App\Models\BusinessProfileReview;
use App\Models\ContentGeneration;
use App\Models\Optimisation;
use App\Models\RemediationRun;
use App\Models\SeoTargetKeywordRanking;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WeeklyReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class WeeklyReportBuilder
{
    public function __construct(private SearchConsoleClient $search, private BusinessProfileClient $business) {}

    /** @return array<string, mixed> */
    public function build(Website $website, Carbon $start, Carbon $end): array
    {
        $search = $this->searchConsole($website, $start, $end);
        $business = $this->googleBusiness($website, $start, $end);
        $rankings = $this->rankings($website, $start, $end);
        $audit = $this->siteAudit($website, $start, $end);
        $work = $this->completedWork($website, $start, $end);
        $opportunities = collect([...($audit['opportunities'] ?? []), ...($rankings['opportunities'] ?? []), ...($search['opportunities'] ?? [])])->sortByDesc('score')->take(5)->values()->all();
        $sections = [];
        foreach (['search_console' => ['Search visibility', $search, 'search'], 'rankings' => ['Keyword movements', $rankings, 'seo'], 'google_business' => ['Google Business', $business, 'business-profile'], 'site_audit' => ['Website health', $audit, 'health']] as $key => [$title, $source, $section]) {
            $sections[$key] = ['title' => $title, 'summary' => $source['summary'], 'url' => route('admin.websites.section', [$website, $section])];
        }

        return [
            'schema_version' => 1,
            'reporting_period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'comparison_start' => $start->copy()->subWeek()->toDateString(), 'comparison_end' => $end->copy()->subWeek()->toDateString(), 'timezone' => config('app.timezone')],
            'search_console' => $search, 'google_business' => $business, 'rankings' => $rankings, 'site_audit' => $audit,
            'completed_work' => $work, 'opportunities' => $opportunities, 'sections' => $sections,
            'metric_cards' => array_values(array_filter([
                $search['metrics']['impressions'] ?? $this->metric('Search impressions', null, null), $search['metrics']['clicks'] ?? $this->metric('Search clicks', null, null),
                $rankings['average_position'] ?? null, $rankings['improved_metric'] ?? null,
                $business['metrics']['impressions'] ?? $this->metric('Google Business impressions', null, null), $audit['health_metric'] ?? $this->metric('Site checks passing (%)', null, null),
            ])),
        ];
    }

    /** @return array{label: string, current: ?float, previous: ?float, change: ?float, percent: ?float, direction: string} */
    public function metric(string $label, ?float $current, ?float $previous, bool $lowerIsBetter = false): array
    {
        $change = $current !== null && $previous !== null ? round($current - $previous, 2) : null;

        return ['label' => $label, 'current' => $current, 'previous' => $previous, 'change' => $change,
            'percent' => $change !== null && $previous > 0 ? round($change / $previous * 100, 1) : null,
            'direction' => $change === null || $change == 0 ? 'neutral' : (($lowerIsBetter ? $change < 0 : $change > 0) ? 'positive' : 'negative')];
    }

    /** @return array<string, mixed> */
    private function searchConsole(Website $website, Carbon $start, Carbon $end): array
    {
        $connection = $website->searchConsoleConnection;
        if (! $connection?->property_url) {
            return ['status' => 'unavailable', 'summary' => 'Connect Google Search Console to include search visibility.'];
        }
        // Search Console final data lags reporting: compare two whole weeks with the same lag.
        $searchEnd = $end->copy()->subDays(2);
        $searchStart = $searchEnd->copy()->subDays(6);
        try {
            $current = $this->search->weeklyPerformance($connection, $searchStart, $searchEnd);
            $previous = $this->search->weeklyPerformance($connection, $searchStart->copy()->subWeek(), $searchEnd->copy()->subWeek());
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'unavailable', 'summary' => 'Google Search data was unavailable when this overview was prepared.'];
        }
        $metrics = [];
        foreach (['impressions' => 'Search impressions', 'clicks' => 'Search clicks', 'ctr' => 'Search click rate (%)', 'position' => 'Average Google position'] as $key => $label) {
            $factor = $key === 'ctr' ? 100 : 1;
            $a = $current['totals'][$key] ?? null;
            $b = $previous['totals'][$key] ?? null;
            $metrics[$key] = $this->metric($label, $a === null ? null : $a * $factor, $b === null ? null : $b * $factor, $key === 'position');
        }
        $queries = $this->searchMovements($current['queries'], $previous['queries']);
        $pages = $this->searchMovements($current['pages'], $previous['pages']);
        $opportunities = collect();
        foreach (['queries' => 'query', 'pages' => 'page'] as $rows => $type) {
            foreach ($current[$rows] as $row) {
                if ($row['impressions'] >= 100 && $row['ctr'] < 0.02) {
                    $opportunities->push(['source' => 'search_console', 'type' => 'low_ctr', 'subject_type' => $type, 'subject' => $row['key'], 'score' => 70, 'title' => 'Improve the search listing for '.$row['key'], 'reason' => $row['impressions'].' impressions with '.round($row['ctr'] * 100, 1).'% clicking through.', 'evidence' => $row]);
                } elseif ($row['impressions'] >= 30 && $row['position'] >= 8 && $row['position'] <= 20) {
                    $opportunities->push(['source' => 'search_console', 'type' => 'near_page_one', 'subject_type' => $type, 'subject' => $row['key'], 'score' => 65, 'title' => 'Improve visibility for '.$row['key'], 'reason' => 'Average position '.round($row['position'], 1).' across '.$row['impressions'].' impressions.', 'evidence' => $row]);
                }
            }
        }
        foreach ($pages['losing'] as $row) {
            $opportunities->push(['source' => 'search_console', 'type' => 'losing_visibility', 'subject_type' => 'page', 'subject' => $row['key'], 'score' => 75, 'title' => 'Review declining visibility for '.$row['key'], 'reason' => $row['impressions']['change'].' impressions and '.$row['clicks']['change'].' clicks compared with the previous week.', 'evidence' => $row]);
        }
        foreach ($pages['gaining'] as $row) {
            $opportunities->push(['source' => 'search_console', 'type' => 'gaining_visibility', 'subject_type' => 'page', 'subject' => $row['key'], 'score' => 60, 'title' => 'Build on growing visibility for '.$row['key'], 'reason' => $row['impressions']['change'].' additional impressions and '.$row['clicks']['change'].' additional clicks compared with the previous week.', 'evidence' => $row]);
        }
        $summary = 'Search data covers '.$searchStart->format('j M').'–'.$searchEnd->format('j M Y').', allowing for Google’s reporting delay. ';
        $summary .= $current['totals'] === null ? 'No final search totals were returned; this is not treated as zero.' : $this->metricSentence($metrics['impressions']).' '.$this->metricSentence($metrics['clicks']);
        $summary .= ' Query and page movements use a sample of up to 250 results per period; omitted results are not treated as losses.';

        return ['status' => 'available', 'period' => ['start' => $searchStart->toDateString(), 'end' => $searchEnd->toDateString(), 'comparison_start' => $searchStart->copy()->subWeek()->toDateString(), 'comparison_end' => $searchEnd->copy()->subWeek()->toDateString()], 'metrics' => $metrics, 'queries' => $queries, 'pages' => $pages, 'sampled' => true, 'opportunities' => $opportunities->sortByDesc('score')->take(5)->values()->all(), 'summary' => $summary];
    }

    /** @param array<int, array<string, mixed>> $current
     * @param  array<int, array<string, mixed>>  $previous
     * @return array{gaining: array, losing: array}
     */
    public function searchMovements(array $current, array $previous): array
    {
        $prior = collect($previous)->keyBy('key');
        $movements = collect($current)->filter(fn (array $row): bool => $prior->has($row['key']))->map(function (array $row) use ($prior): array {
            $old = $prior[$row['key']];

            return ['key' => $row['key'], 'impressions' => $this->metric('Impressions', $row['impressions'], $old['impressions']), 'clicks' => $this->metric('Clicks', $row['clicks'], $old['clicks'])];
        })->filter(fn (array $row): bool => (abs($row['impressions']['change']) >= 30 && abs($row['impressions']['percent'] ?? 100) >= 20) || abs($row['clicks']['change']) >= 5);

        return ['gaining' => $movements->filter(fn ($row) => $row['impressions']['change'] > 0 || ($row['impressions']['change'] == 0 && $row['clicks']['change'] > 0))->sortByDesc('impressions.change')->take(3)->values()->all(), 'losing' => $movements->filter(fn ($row) => $row['impressions']['change'] < 0 || ($row['impressions']['change'] == 0 && $row['clicks']['change'] < 0))->sortBy('impressions.change')->take(3)->values()->all()];
    }

    /** @return array<string, mixed> */
    private function googleBusiness(Website $website, Carbon $start, Carbon $end): array
    {
        $connection = $website->businessProfileConnection;
        if (! $connection?->location_name) {
            return ['status' => 'unavailable', 'summary' => 'Select a Google Business Profile location to include its performance.'];
        }
        $metrics = [];
        try {
            $raw = $this->business->weeklyPerformance($connection, $start->copy()->subWeek(), $end);
            $current = $this->businessTotals($raw, $start, $end);
            $previous = $this->businessTotals($raw, $start->copy()->subWeek(), $end->copy()->subWeek());
            foreach (['impressions' => 'Google Business impressions', 'search_impressions' => 'Business Search impressions', 'maps_impressions' => 'Business Maps impressions', 'website_clicks' => 'Business website clicks', 'calls' => 'Call clicks', 'directions' => 'Direction requests'] as $key => $label) {
                $metrics[$key] = $this->metric($label, $current[$key], $previous[$key]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        $reviews = null;
        try {
            $reviews = $this->business->reviewSummary($connection);
        } catch (Throwable $exception) {
            report($exception);
        }
        $prior = WeeklyReport::query()->where('website_id', $website->id)->whereDate('period_end', $end->copy()->subWeek())->whereNotNull('generated_at')->first();
        $sameLocation = data_get($prior?->snapshot, 'google_business.location') === $connection->location_name;
        $reviewMetrics = ['count' => $this->metric('Google reviews', $reviews['count'] ?? null, $sameLocation ? data_get($prior?->snapshot, 'google_business.reviews.count.current') : null), 'rating' => $this->metric('Average star rating', $reviews['rating'] ?? null, $sameLocation ? data_get($prior?->snapshot, 'google_business.reviews.rating.current') : null)];
        $summary = isset($metrics['impressions']) ? $this->metricSentence($metrics['impressions']).' '.$this->metricSentence($metrics['website_clicks']) : 'Google Business performance was unavailable when this overview was prepared.';
        $summary .= ' Incomplete daily series are shown as unavailable, not zero.';
        if ($reviews !== null && $reviews['count'] !== null) {
            $summary .= ' At report preparation, the profile had '.$reviews['count'].' reviews'.($reviews['rating'] !== null ? ' with an average '.$reviews['rating'].' star rating.' : '.');
        }

        return ['status' => $metrics === [] ? 'unavailable' : 'available', 'location' => $connection->location_name, 'metrics' => $metrics, 'reviews' => $reviewMetrics, 'reviews_observed_at' => now()->toIso8601String(), 'summary' => $summary];
    }

    /** @param array<string, mixed> $raw
     * @return array<string, ?float>
     */
    public function businessTotals(array $raw, Carbon $start, Carbon $end): array
    {
        $series = collect($raw['multiDailyMetricTimeSeries'] ?? [])->flatMap(fn ($group) => $group['dailyMetricTimeSeries'] ?? [])->keyBy('dailyMetric');
        $sum = function (array $names) use ($series, $start, $end): ?float {
            $total = 0;
            foreach ($names as $name) {
                $days = collect(data_get($series->get($name), 'timeSeries.datedValues', []))->mapWithKeys(fn (array $day): array => [sprintf('%04d-%02d-%02d', data_get($day, 'date.year'), data_get($day, 'date.month'), data_get($day, 'date.day')) => (float) ($day['value'] ?? 0)])
                    ->filter(fn ($value, $date) => $date >= $start->toDateString() && $date <= $end->toDateString());
                if ($days->count() !== 7) {
                    return null;
                }
                $total += $days->sum();
            }

            return (float) $total;
        };
        $search = ['BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH'];
        $maps = ['BUSINESS_IMPRESSIONS_DESKTOP_MAPS', 'BUSINESS_IMPRESSIONS_MOBILE_MAPS'];

        return ['impressions' => $sum([...$search, ...$maps]), 'search_impressions' => $sum($search), 'maps_impressions' => $sum($maps), 'website_clicks' => $sum(['WEBSITE_CLICKS']), 'calls' => $sum(['CALL_CLICKS']), 'directions' => $sum(['BUSINESS_DIRECTION_REQUESTS'])];
    }

    /** @return array<string, mixed> */
    private function rankings(Website $website, Carbon $start, Carbon $end): array
    {
        $targets = $website->seoTargetKeywords()->where('created_at', '<=', $end)->where(fn ($query) => $query->whereNull('archived_at')->orWhere('archived_at', '>', $end))
            ->with(['rankings' => fn ($query) => $query->where('website_id', $website->id)->whereBetween('observed_at', [$start->copy()->subWeeks(2), $end])
                ->where('location_code', (int) config('services.dataforseo.location_code'))->where('language_code', (string) config('services.dataforseo.language_code'))->where('device', 'desktop')
                ->whereIn('status', [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND])->latest('observed_at')->latest('id')])->orderBy('id')->get();
        $counts = array_fill_keys(['ranking', 'top_3', 'positions_4_10', 'positions_11_20', 'outside_top_20', 'missing', 'improved', 'declined', 'unchanged', 'awaiting_comparison'], 0);
        $rows = collect();
        $opportunities = [];
        $priorImproved = 0;
        $priorComparable = 0;
        $positions = collect();
        $priorPositions = collect();
        foreach ($targets as $target) {
            $current = $target->rankings->first(fn ($row) => $row->observed_at->betweenIncluded($start, $end));
            $previous = $target->rankings->first(fn ($row) => $row->observed_at->betweenIncluded($start->copy()->subWeek(), $end->copy()->subWeek()));
            $older = $target->rankings->first(fn ($row) => $row->observed_at->betweenIncluded($start->copy()->subWeeks(2), $end->copy()->subWeeks(2)));
            $a = $current?->position;
            $b = $previous?->position;
            if ($previous && $older) {
                $priorComparable++;
            }
            if ($previous && $older && ($b ?? 101) < ($older->position ?? 101)) {
                $priorImproved++;
            }
            if ($a !== null) {
                $positions->push($a);
            }
            if ($b !== null) {
                $priorPositions->push($b);
            }
            if (! $current) {
                $counts['missing']++;
            } else {
                $counts[$a === null || $a > 20 ? 'outside_top_20' : ($a <= 3 ? 'top_3' : ($a <= 10 ? 'positions_4_10' : 'positions_11_20'))]++;
                $counts['ranking'] += $a !== null ? 1 : 0;
            }
            $movement = ! $current || ! $previous ? 'awaiting_comparison' : (($a ?? 101) === ($b ?? 101) ? 'unchanged' : (($a ?? 101) < ($b ?? 101) ? 'improved' : 'declined'));
            $counts[$movement]++;
            $crossings = [];
            if ($current && $previous) {
                foreach ([3, 10, 20] as $boundary) {
                    if (($a ?? 101) <= $boundary && ($b ?? 101) > $boundary) {
                        $crossings[] = 'Entered top '.$boundary;
                    } elseif (($a ?? 101) > $boundary && ($b ?? 101) <= $boundary) {
                        $crossings[] = 'Dropped out of top '.$boundary;
                    }
                }
            }
            $change = $a !== null && $b !== null ? $b - $a : null;
            $row = ['target_id' => $target->id, 'term' => $target->term, 'current' => $a, 'previous' => $b, 'observed_at' => $current?->observed_at->toIso8601String(), 'previous_observed_at' => $previous?->observed_at->toIso8601String(), 'movement' => $movement, 'change' => $change, 'crossings' => $crossings, 'significant' => count($crossings) > 0 || abs($change ?? 0) >= 3];
            $rows->push($row);
            if ($a !== null && $a >= 11 && $a <= 20) {
                $opportunities[] = ['source' => 'rankings', 'type' => 'near_page_one', 'subject_id' => $target->id, 'score' => 80, 'title' => 'Improve visibility for “'.$target->term.'”', 'reason' => 'This tracked keyword sits just outside page one at position '.$a.'.', 'evidence' => $row];
            }
        }
        $summary = 'Of '.$targets->count().' tracked keywords, '.$counts['improved'].' improved, '.$counts['declined'].' declined and '.$counts['unchanged'].' were unchanged. '.$counts['awaiting_comparison'].' lack comparable checks in both weeks.';
        $largestGain = $rows->where('change', '>', 0)->sortByDesc('change')->first();
        $largestLoss = $rows->where('change', '<', 0)->sortBy('change')->first();
        if ($largestGain && $largestGain['significant']) {
            $summary .= ' “'.$largestGain['term'].'” moved from '.$largestGain['previous'].' to '.$largestGain['current'].'.';
        }
        if ($largestLoss && $largestLoss['significant']) {
            $summary .= ' “'.$largestLoss['term'].'” declined from '.$largestLoss['previous'].' to '.$largestLoss['current'].'.';
        }
        foreach ($rows->filter(fn ($row) => $row['crossings'] !== [])->take(2) as $row) {
            $summary .= ' “'.$row['term'].'”: '.implode('; ', $row['crossings']).'.';
        }
        $comparableAverage = $targets->isNotEmpty() && $positions->count() === $targets->count() && $priorPositions->count() === $targets->count();

        return ['status' => 'available', 'total' => $targets->count(), ...$counts, 'movements' => $rows->where('significant', true)->sortByDesc(fn ($row) => count($row['crossings']) * 100 + abs($row['change'] ?? 0))->take(10)->values()->all(), 'largest_gain' => $largestGain, 'largest_loss' => $largestLoss,
            'average_position' => $this->metric('Average tracked position', $positions->isEmpty() ? null : round($positions->avg(), 1), $comparableAverage ? round($priorPositions->avg(), 1) : null, true),
            'improved_metric' => $this->metric('Keywords improved', $counts['improved'], $targets->isNotEmpty() && $priorComparable === $targets->count() && $counts['awaiting_comparison'] === 0 ? $priorImproved : null), 'opportunities' => array_slice($opportunities, 0, 5), 'summary' => $summary];
    }

    /** @return array<string, mixed> */
    private function siteAudit(Website $website, Carbon $start, Carbon $end): array
    {
        $base = WebsiteHealthReport::query()->where('website_id', $website->id)->where('status', WebsiteHealthReport::STATUS_COMPLETED)->with('pages')->latest('completed_at')->latest('id');
        $current = (clone $base)->where('completed_at', '<=', $end)->first();
        $previous = (clone $base)->where('completed_at', '<', $start)->first();
        if (! $current) {
            return ['status' => 'unavailable', 'summary' => 'No completed website health audit is available for this period.'];
        }
        $checks = $this->auditChecks($current);
        $old = $previous ? $this->auditChecks($previous) : collect();
        $issues = $checks->filter(fn ($check) => in_array($check['status'] ?? '', ['failed', 'warning'], true));
        $new = $previous ? $issues->filter(fn ($check, $key) => isset($old[$key]) && ($old[$key]['status'] ?? '') === 'passed')->count() : null;
        $resolved = $previous ? $old->filter(fn ($check, $key) => in_array($check['status'] ?? '', ['failed', 'warning'], true) && isset($checks[$key]) && ($checks[$key]['status'] ?? '') === 'passed')->count() : null;
        $discovered = $issues->diffKeys($old)->count();
        $important = $issues->where('status', 'failed');
        $opportunities = $important->take(3)->map(fn ($check, $key) => ['source' => 'site_audit', 'type' => 'failed_check', 'subject_id' => $current->id, 'check_key' => $key, 'score' => 100, 'title' => 'Resolve '.$check['label'], 'reason' => $check['message'] ?? 'This check failed in the latest audit.', 'evidence' => ['report_id' => $current->id, 'checked_at' => $current->completed_at->toIso8601String(), ...$check]])->values()->all();
        $summary = 'Latest audit: '.$current->completed_at->format('j M Y').'. '.$important->count().' important issues remain. ';
        $summary .= $previous ? $resolved.' issues now pass, '.$new.' previously passing checks now need attention, and '.$discovered.' issues were found in newly checked areas.' : 'This is the first audit baseline; changes cannot yet be compared.';
        if ($current->completed_at->lt($start)) {
            $summary .= ' No new audit was completed this week.';
        }
        $score = fn (Collection $items): ?float => $items->isEmpty() ? null : round($items->where('status', 'passed')->count() / $items->count() * 100, 1);

        return ['status' => 'available', 'report_id' => $current->id, 'audited_at' => $current->completed_at->toIso8601String(), 'overall_status' => $current->overall_status, 'new_issues' => $previous ? $new + $discovered : null, 'newly_discovered' => $discovered, 'resolved_issues' => $resolved, 'important_issues' => $important->count(), 'findings' => $issues->sortBy(fn ($check) => $check['status'] === 'failed' ? 0 : 1)->take(8)->values()->all(), 'health_metric' => $this->metric('Site checks passing (%)', $score($checks), $previous && $checks->keys()->sort()->values()->all() === $old->keys()->sort()->values()->all() ? $score($old) : null), 'opportunities' => $opportunities, 'summary' => $summary];
    }

    /** @return Collection<string, array<string, mixed>> */
    private function auditChecks(WebsiteHealthReport $report): Collection
    {
        $checks = collect($report->checks ?? [])->mapWithKeys(fn ($check) => ['site:'.($check['category'] ?? '').':'.($check['key'] ?? $check['label']) => $check]);
        foreach ($report->pages as $page) {
            foreach ($page->checks ?? [] as $check) {
                $checks->put($page->url.':'.($check['category'] ?? '').':'.($check['key'] ?? $check['label']), [...$check, 'page' => $page->url]);
            }
        }

        return $checks->filter(fn ($check) => in_array($check['status'] ?? '', ['passed', 'warning', 'failed'], true));
    }

    /** @return array<int, array{source: string, source_id: int, title: string, completed_at: string, url: string}> */
    private function completedWork(Website $website, Carbon $start, Carbon $end): array
    {
        $items = collect();
        $add = function (string $source, int $id, string $title, Carbon $at, string $section) use ($items, $website): void {
            $items->push(['source' => $source, 'source_id' => $id, 'title' => (string) str($title)->limit(300), 'completed_at' => $at->toIso8601String(), 'url' => route('admin.websites.section', [$website, $section])]);
        };
        foreach (Optimisation::query()->where('website_id', $website->id)->whereBetween('deployed_at', [$start, $end])->where(fn ($query) => $query->whereNull('rolled_back_at')->orWhere('rolled_back_at', '>', $end))->get() as $work) {
            $add('optimisation', $work->id, str($work->type->value)->replace('_', ' ')->ucfirst().' updated on '.$work->url, $work->deployed_at, 'content');
        }
        foreach (ContentGeneration::query()->whereHas('plan', fn ($query) => $query->where('website_id', $website->id))->where('status', 'completed')->whereRaw('COALESCE(merged_at, completed_at) BETWEEN ? AND ?', [$start, $end])->with('contentRequests')->get() as $work) {
            $title = $work->contentRequests->pluck('instructions')->filter()->map(fn ($text) => str($text)->limit(160))->implode('; ');
            $add('content_generation', $work->id, ($work->merged_at ? 'Content changes merged' : 'Content work marked completed manually').($title ? ': '.$title : '.'), $work->merged_at ?? $work->completed_at, 'content');
        }
        foreach (RemediationRun::query()->whereHas('report', fn ($query) => $query->where('website_id', $website->id))->where('status', 'completed')->whereRaw('COALESCE(merged_at, completed_at) BETWEEN ? AND ?', [$start, $end])->get() as $work) {
            $add('remediation', $work->id, $work->merged_at ? 'Website fixes merged for review in the health report.' : 'Website fixes marked completed manually.', $work->merged_at ?? $work->completed_at, 'health');
        }
        foreach (BusinessProfilePost::query()->whereHas('connection', fn ($query) => $query->where('website_id', $website->id))->where('status', 'published')->whereBetween('published_at', [$start, $end])->get() as $work) {
            $add('business_post', $work->id, 'Google Business post published: '.str($work->topic)->limit(160), $work->published_at, 'business-profile');
        }
        foreach (BusinessProfileRecommendation::query()->whereHas('audit.connection', fn ($query) => $query->where('website_id', $website->id))->where('status', 'applied')->whereBetween('applied_at', [$start, $end])->get() as $work) {
            $add('business_update', $work->id, 'Google Business updated: '.$work->title, $work->applied_at, 'business-profile');
        }

        foreach (BusinessProfileReview::query()->whereHas('connection', fn ($query) => $query->where('website_id', $website->id))->where('reply_status', 'replied')->whereBetween('replied_at', [$start, $end])->get() as $work) {
            $add('business_review_reply', $work->id, 'Reply published to a Google Business customer review.', $work->replied_at, 'business-profile');
        }

        return $items->sortByDesc('completed_at')->values()->all();
    }

    /** @param array<string, mixed> $metric */
    private function metricSentence(array $metric): string
    {
        if ($metric['current'] === null) {
            return $metric['label'].' are unavailable for the complete week.';
        }
        $sentence = $metric['label'].': '.$metric['current'];
        if ($metric['previous'] !== null) {
            $sentence .= ', previously '.$metric['previous'].' ('.($metric['change'] >= 0 ? '+' : '').$metric['change'].($metric['percent'] !== null ? '; '.$metric['percent'].'%' : '').')';
        } else {
            $sentence .= '; no comparable previous period';
        }

        return $sentence.'.';
    }
}

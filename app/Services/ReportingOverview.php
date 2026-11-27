<?php

namespace App\Services;

use App\Models\AgentContentTask;
use App\Models\ReportingSnapshot;
use App\Models\SearchConsoleDailyMetric;
use App\Models\SeoTargetKeywordRanking;
use App\Models\SeoWin;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportingOverview
{
    /** @param Collection<int, Website> $sites */
    public function rows(Collection $sites): Collection
    {
        $ids = $sites->pluck('id');
        $end = ReportingPeriod::cutoff();
        $start = $end->subDays(6);
        $search = SearchConsoleDailyMetric::query()->whereIn('website_id', $ids)->where('data_status', 'observed')
            ->whereBetween('date', [$start->toDateString(), $end->endOfDay()])
            ->selectRaw('website_id, search_console_connection_id, property_hash, COUNT(*) as days, SUM(clicks) as total')
            ->groupBy('website_id', 'search_console_connection_id', 'property_hash')->get()->groupBy('website_id');
        $checks = SeoTargetKeywordRanking::query()->whereIn('website_id', $ids)
            ->whereHas('targetKeyword', fn ($query) => $query->whereNull('archived_at')->whereColumn('website_id', 'seo_target_keyword_rankings.website_id'))
            ->where('location_code', config('services.dataforseo.location_code'))->where('language_code', config('services.dataforseo.language_code'))
            ->where('device', 'desktop')->whereIn('status', ['ranked', 'not_found'])
            ->whereBetween('observed_at', [today()->subDays(6), now()])
            ->selectRaw('website_id, position, ROW_NUMBER() OVER (PARTITION BY seo_target_keyword_id ORDER BY observed_at DESC, id DESC) AS sequence_number');
        $ranks = DB::query()->fromSub($checks, 'checks')->where('sequence_number', 1)
            ->selectRaw('website_id, SUM(CASE WHEN position BETWEEN 1 AND 10 THEN 1 ELSE 0 END) AS top_ten')
            ->groupBy('website_id')->get()->keyBy('website_id');
        $latest = ReportingSnapshot::query()->whereIn('website_id', $ids)
            ->selectRaw('website_id, source, property_hash, MAX(period_end) as period_end')
            ->groupBy('website_id', 'source', 'property_hash');
        $snapshots = ReportingSnapshot::query()->joinSub($latest, 'latest', function ($join): void {
            foreach (['website_id', 'source', 'property_hash', 'period_end'] as $column) {
                $join->on('reporting_snapshots.'.$column, '=', 'latest.'.$column);
            }
        })->select('reporting_snapshots.*')->get()->groupBy('website_id');

        return $sites->map(function (Website $site) use ($search, $snapshots, $ranks): array {
            $connection = $site->searchConsoleConnection;
            $gaProperty = config('reporting.analytics_properties.'.$site->id);
            $saved = $snapshots->get($site->id, collect());
            $index = $saved->where('source', 'indexing')->firstWhere('property', '===', $connection?->property_url);
            $analytics = $gaProperty ? $saved->where('source', 'analytics')->firstWhere('property', '===', (string) $gaProperty) : null;
            $daily = $search->get($site->id, collect())->where('search_console_connection_id', $connection?->id)
                ->firstWhere('property_hash', hash('sha256', (string) $connection?->property_url));
            $report = $site->latestWeeklyReport;
            $notices = [];
            if ($site->is_active && ($connection || $gaProperty || $site->weekly_ranking_reports_enabled || $site->seo_weekly_snapshots_enabled)) {
                if (! $connection?->property_url || $connection->access_denied_at) {
                    $notices['google-connection'] = $connection?->access_denied_at ? 'Restore Search Console access' : 'Connect Search Console';
                } elseif (! $index || $index->period_end->lt(today()->subWeek())) {
                    $notices['indexing'] = 'Index checks are due';
                }
                if ($index && $index->metrics['indexed'] < $index->metrics['checked']) {
                    $notices['index-verdicts'] = 'Review Google index verdicts';
                }
                if ($gaProperty && (! $analytics || $analytics->period_end->lt(today()->startOfWeek(Carbon::MONDAY)->subDay()))) {
                    $notices['traffic-title'] = 'GA4 totals are due';
                }
                if ($site->weekly_ranking_reports_enabled && (! $report || $report->period_end->lt(today()->subDays(8)))) {
                    $notices['weekly'] = 'Weekly report overdue';
                }
            }

            return ['site' => $site, 'index' => $index, 'analytics' => $analytics, 'clicks' => $daily?->total,
                'ranks' => $ranks->get($site->id), 'days' => (int) ($daily?->days ?? 0), 'report' => $report, 'notices' => $notices];
        });
    }

    public function attention(Collection $rows, ?Collection $workAttention = null): Collection
    {
        $sites = $rows->pluck('site')->keyBy('id');
        $items = collect();
        foreach ($rows as $row) {
            foreach ($row['notices'] as $anchor => $notice) {
                $items->push(['site' => $row['site']->name, 'title' => $notice, 'url' => $anchor === 'weekly' ? route('admin.reporting.weekly', $row['site']) : route('admin.reporting.site', $row['site']).'#'.$anchor]);
            }
        }
        $alerts = SeoWin::whereIn('website_id', $sites->keys())->where('category', 'alert')->whereNull('dismissed_at')
            ->whereNull('shared_at')->latest('confirmed_at')->limit(20)->get();
        foreach ($alerts as $alert) {
            $items->push(['site' => $sites[$alert->website_id]->name, 'title' => $alert->title, 'url' => route('admin.reporting.site', $alert->website_id).'#rankings']);
        }
        $tasks = AgentContentTask::whereIn('website_id', $sites->keys())->whereIn('status', ['blocked', 'draft_pr_reported'])
            ->whereHas('contentRequest', fn ($query) => $query->whereNull('manual_completed_at')->whereNotNull('manual_started_at')->whereColumn('manual_taken_by', 'agent_content_tasks.user_id'))
            ->latest('updated_at')->limit(20)->get();
        foreach ($tasks as $task) {
            $items->push(['site' => $sites[$task->website_id]->name, 'title' => $task->status === 'blocked' ? 'Codex task blocked: '.$task->blocked_reason : 'Review the Codex draft pull request',
                'url' => $task->status === 'draft_pr_reported' && str_starts_with((string) $task->pull_request_url, 'https://github.com/') ? $task->pull_request_url : route('admin.content-queue.index', ['task' => $task->content_request_id])]);
        }
        $workAttention ??= app(DashboardWorkActivity::class)->forWebsites($sites->keys()->all())['attention'];
        foreach ($workAttention as $work) {
            $items->push(['site' => $work['website'], 'title' => $work['type'].' · '.$work['status'].': '.Str::limit($work['reason'], 300), 'url' => $work['url']]);
        }

        return $items;
    }
}

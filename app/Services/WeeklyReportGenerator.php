<?php

namespace App\Services;

use App\Ai\Agents\WeeklyOverviewWriter;
use App\Models\Website;
use App\Models\WeeklyReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Enums\Lab;
use Throwable;

class WeeklyReportGenerator
{
    public function __construct(private WeeklyReportBuilder $builder, private WeeklyOverviewWriter $writer) {}

    public function generate(Website $website, Carbon $dispatchDate): WeeklyReport
    {
        $end = $dispatchDate->copy()->subDay()->endOfDay();
        $start = $end->copy()->subDays(6)->startOfDay();

        return Cache::lock('weekly-overview:'.$website->id.':'.$start->toDateString(), 290)->block(5, function () use ($website, $start, $end): WeeklyReport {
            $key = ['website_id' => $website->id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()];
            $report = WeeklyReport::query()->where('website_id', $website->id)->whereDate('period_start', $start)->whereDate('period_end', $end)->first();
            if ($report?->generated_at) {
                return $report;
            }
            if (! $report) {
                $snapshot = $this->builder->build($website, $start, $end);
                $priority = $snapshot['opportunities'][0] ?? null;
                $report = WeeklyReport::query()->create([...$key, 'snapshot' => $snapshot, 'recommended_priority' => $priority]);
            }
            $snapshot = $report->snapshot;
            $facts = collect($snapshot['sections'])->pluck('summary')->all();
            $workCount = count($snapshot['completed_work']);
            $work = $workCount === 0 ? 'No completed Sitewell work was recorded during this period.' : 'Sitewell recorded '.$workCount.' completed work items: '.collect($snapshot['completed_work'])->take(3)->pluck('title')->implode('; ').'.';
            $next = $report->recommended_priority ? 'Priority for next week: '.$report->recommended_priority['title'].'. '.$report->recommended_priority['reason'] : 'There is not enough evidence to recommend a specific next priority yet.';
            $overview = implode("\n\n", [...$facts, $work]);
            $source = 'deterministic';
            try {
                $response = $this->writer->prompt(json_encode(['reporting_period' => $snapshot['reporting_period'], 'facts' => $facts, 'completed_work' => $work, 'recommended_priority' => $next], JSON_THROW_ON_ERROR), provider: Lab::OpenAI, timeout: 30);
                $text = trim($response->text);
                if ($text !== '' && mb_strlen($text) <= 2500 && ! preg_match('/https?:\/\//i', $text)) {
                    $overview = $text;
                    $source = 'ai';
                }
            } catch (Throwable $exception) {
                report($exception);
            }
            $report->update(['overview' => $overview."\n\n".$next, 'narrative_source' => $source, 'generated_at' => now()]);

            return $report;
        });
    }
}

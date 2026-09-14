<?php

namespace App\Jobs;

use App\Mail\WeeklyRankingReport;
use App\Models\Website;
use App\Services\RankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use App\Services\WeeklyReportGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendWeeklyRankingReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 15;

    public int $timeout = 280;

    public Carbon $overviewDispatchDate;

    public int $maxExceptions = 3;

    public Carbon $freshnessDeadline;

    public Carbon $rankingPeriodStart;

    public int $uniqueFor = 604800;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public Website $website)
    {
        $this->overviewDispatchDate = today();
        $this->freshnessDeadline = now()->addHour();
        $this->rankingPeriodStart = now()->startOfWeek();
    }

    public function uniqueId(): string
    {
        return $this->website->id.':'.$this->rankingPeriodStart->toDateString();
    }

    public function handle(RankingReportBuilder $builder, WebsiteMailRecipients $recipients): void
    {
        $this->website->refresh();
        $report = $builder->build($this->website);
        $report['targetKeywords'] = $report['targetKeywords']->map(function (array $item): array {
            $item['is_stale'] = ! $item['latest'] || $item['latest']->observed_at->lessThan($this->rankingPeriodStart);

            return $item;
        });

        if ($this->website->seo_weekly_snapshots_enabled
            && $report['targetKeywords']->contains(fn (array $item): bool => $item['is_stale'])
            && now()->lessThan($this->freshnessDeadline)) {
            $this->release(300);

            return;
        }

        $overview = app(WeeklyReportGenerator::class)->generate($this->website, $this->overviewDispatchDate ?? $this->rankingPeriodStart);

        foreach ($recipients->forReports($this->website) as $recipient) {
            Mail::to($recipient)->send(new WeeklyRankingReport($this->website, $report, $overview));
        }
    }
}

<?php

namespace App\Jobs;

use App\Mail\WeeklyRankingReport;
use App\Models\Website;
use App\Services\RankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendWeeklyRankingReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 604800;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public Website $website) {}

    public function uniqueId(): string
    {
        return $this->website->id.':'.now()->startOfWeek()->toDateString();
    }

    public function handle(RankingReportBuilder $builder, WebsiteMailRecipients $recipients): void
    {
        $report = $builder->build($this->website);

        foreach ($recipients->for($this->website) as $recipient) {
            Mail::to($recipient)->send(new WeeklyRankingReport($this->website, $report));
        }
    }
}

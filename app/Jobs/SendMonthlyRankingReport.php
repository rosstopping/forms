<?php

namespace App\Jobs;

use App\Mail\MonthlyRankingReport;
use App\Models\Website;
use App\Services\MonthlyRankingReportBuilder;
use App\Services\WebsiteMailRecipients;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendMonthlyRankingReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 2678400;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public Website $website) {}

    public function uniqueId(): string
    {
        return $this->website->id.':'.now()->startOfMonth()->toDateString();
    }

    public function handle(MonthlyRankingReportBuilder $builder, WebsiteMailRecipients $recipients): void
    {
        $report = $builder->build($this->website);
        $monthlyRecipients = $recipients->withoutViewers($this->website, $recipients->forReports($this->website));
        foreach ($monthlyRecipients as $recipient) {
            Mail::to($recipient)->send(new MonthlyRankingReport($this->website, $report));
        }
    }
}

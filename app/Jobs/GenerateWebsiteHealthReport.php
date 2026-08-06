<?php

namespace App\Jobs;

use App\Mail\WebsiteHealthReportReady;
use App\Models\User;
use App\Models\WebsiteHealthReport;
use App\Services\WebsiteHealthAuditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateWebsiteHealthReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public WebsiteHealthReport $report) {}

    public function uniqueId(): string
    {
        return (string) $this->report->website_id;
    }

    public function handle(WebsiteHealthAuditor $auditor): void
    {
        $this->report->update([
            'status' => WebsiteHealthReport::STATUS_RUNNING,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $result = $auditor->audit($this->report->website);

            $this->report->update([
                ...$result,
                'status' => WebsiteHealthReport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->sendReport();
        } catch (Throwable $exception) {
            $this->report->update([
                'status' => WebsiteHealthReport::STATUS_FAILED,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    protected function sendReport(): void
    {
        $website = $this->report->website()->with('owner')->firstOrFail();
        $recipients = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('email')
            ->push($website->owner?->email)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new WebsiteHealthReportReady($this->report->fresh(['website'])));
        }

        $this->report->update(['emailed_at' => now()]);
    }
}

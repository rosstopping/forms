<?php

namespace App\Jobs;

use App\Models\WebsiteAudit;
use App\Services\MarketingAuditResearch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateWebsiteAuditFullReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public WebsiteAudit $audit) {}

    public function uniqueId(): string
    {
        return (string) $this->audit->id;
    }

    /**
     * Execute the job.
     */
    public function handle(MarketingAuditResearch $research): void
    {
        $audit = $this->audit->fresh();
        if ($audit === null || $audit->status !== WebsiteAudit::STATUS_COMPLETED || data_get($audit->insights, 'full_report.status') !== 'queued') {
            return;
        }

        $audit->update(['insights' => [...$audit->insights, 'full_report' => [...$audit->insights['full_report'], 'status' => 'running']]]);
        $insights = $research->forFullReport($audit);
        DB::transaction(function () use ($audit, $insights): void {
            $saved = WebsiteAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $saved->update(['insights' => [...$saved->insights, ...$insights, 'full_report' => [
                ...$saved->insights['full_report'],
                'status' => 'completed',
                'completed_at' => now()->toIso8601String(),
            ]]]);
            if (data_get($saved->insights, 'ai_visibility.status') === 'pending') {
                CheckMarketingAuditAiVisibility::dispatch($saved)->afterCommit();
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function (): void {
            $audit = WebsiteAudit::query()->lockForUpdate()->find($this->audit->id);
            if ($audit === null || ! in_array(data_get($audit->insights, 'full_report.status'), ['queued', 'running'], true)) {
                return;
            }
            $audit->update(['insights' => [...$audit->insights, 'full_report' => [...$audit->insights['full_report'], 'status' => 'failed']]]);
        });
    }
}

<?php

namespace App\Jobs;

use App\Models\WebsiteAudit;
use App\Services\ServicePageFinder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckAuditServicePages implements ShouldBeUnique, ShouldQueue
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

    public function handle(ServicePageFinder $finder): void
    {
        $audit = $this->audit->fresh();
        if ($audit === null || $audit->status !== WebsiteAudit::STATUS_COMPLETED
            || data_get($audit->insights, 'full_report.status') !== 'completed'
            || data_get($audit->insights, 'service_pages.status') !== 'queued') {
            return;
        }
        $this->save(['status' => 'running']);
        try {
            $result = $finder->cachedFind(data_get($audit->insights, 'audited_url', $audit->website_url));
            $this->save(['status' => 'completed', 'result' => $result]);
        } catch (Throwable $exception) {
            report($exception);
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->save(['status' => 'unavailable']);
    }

    private function save(array $value): void
    {
        DB::transaction(function () use ($value): void {
            $audit = WebsiteAudit::query()->lockForUpdate()->find($this->audit->id);
            if ($audit !== null && in_array(data_get($audit->insights, 'service_pages.status'), ['queued', 'running'], true)) {
                $audit->update(['insights' => [...$audit->insights, 'service_pages' => $value]]);
            }
        });
    }
}

<?php

namespace App\Jobs;

use App\Models\CompetitorAudit;
use App\Services\CompetitorAuditService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessCompetitorAuditStage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public CompetitorAudit $audit, public string $stage) {}

    public function uniqueId(): string
    {
        return $this->audit->id.':'.$this->stage;
    }

    public function handle(CompetitorAuditService $service): void
    {
        Cache::lock('competitor-audit-processing:'.$this->audit->id, 270)->block(3, function () use ($service): void {
            $this->audit->refresh();
            if ($this->audit->competitor->excluded) {
                $this->audit->update(['status' => 'failed', 'errors' => ['competitor' => 'Competitor excluded. Restore it to retry.']]);

                return;
            }
            $service->process($this->audit, $this->stage);
            if ($next = $service->nextStage($this->audit)) {
                self::dispatch($this->audit, $next)->afterCommit();
            } else {
                $this->audit->update(['status' => $this->audit->pages()->where('status', 'unavailable')->exists() ? 'completed_with_errors' : 'completed', 'completed_at' => now()]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->audit->refresh();
        $this->audit->update(['status' => 'failed', 'errors' => [...($this->audit->errors ?? []), $this->stage => 'This stage failed. Retry the audit to resume saved progress.']]);
    }
}

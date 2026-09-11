<?php

namespace App\Jobs;

use App\Models\BacklinkAudit;
use App\Services\BacklinkAuditService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessBacklinkAuditStage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public BacklinkAudit $audit, public string $stage) {}

    public function uniqueId(): string
    {
        return $this->audit->id.':'.$this->stage;
    }

    /**
     * Execute the job.
     */
    public function handle(BacklinkAuditService $service): void
    {
        Cache::lock('backlink-audit-processing:'.$this->audit->id, 270)->block(3, function () use ($service): void {
            $this->audit->refresh();
            $service->process($this->audit, $this->stage);
            if ($next = $service->nextStage($this->audit)) {
                self::dispatch($this->audit, $next)->afterCommit();
            } else {
                $partial = $this->audit->pages()->where('status', 'unavailable')->exists() || ($this->audit->errors ?? []) !== [];
                $this->audit->update(['status' => $partial ? 'completed_with_errors' : 'completed', 'completed_at' => now()]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->audit->refresh();
        $this->audit->update(['status' => 'failed', 'errors' => [...($this->audit->errors ?? []), $this->stage => 'This stage failed. Retry the audit to resume saved progress.']]);
    }
}

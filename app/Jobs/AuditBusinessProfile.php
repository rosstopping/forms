<?php

namespace App\Jobs;

use App\Models\BusinessProfileAudit;
use App\Services\BusinessProfileAuditor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AuditBusinessProfile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public BusinessProfileAudit $audit) {}

    public function uniqueId(): string
    {
        return (string) $this->audit->business_profile_connection_id;
    }

    /**
     * Execute the job.
     */
    public function handle(BusinessProfileAuditor $auditor): void
    {
        $this->audit->update(['status' => BusinessProfileAudit::STATUS_RUNNING, 'started_at' => now(), 'error' => null]);
        try {
            $result = $auditor->audit($this->audit->connection);
            $this->audit->recommendations()->delete();
            $this->audit->recommendations()->createMany($result['recommendations']);
            $this->audit->update(['status' => BusinessProfileAudit::STATUS_COMPLETED, 'overall_status' => $result['recommendations'] === [] ? 'healthy' : 'needs_attention', 'snapshot' => $result['snapshot'], 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $this->audit->update(['status' => BusinessProfileAudit::STATUS_FAILED, 'error' => $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }
    }
}

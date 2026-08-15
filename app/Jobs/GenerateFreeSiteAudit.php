<?php

namespace App\Jobs;

use App\Mail\FreeSiteAuditResults;
use App\Models\Prospect;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GenerateFreeSiteAudit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 75;

    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public Prospect $prospect) {}

    public function uniqueId(): string
    {
        return (string) $this->prospect->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ProspectWebsiteAnalyzer $analyzer): void
    {
        $this->prospect->update(['analysis_status' => 'running', 'analysis_error' => null]);
        $analysis = $analyzer->analyze((string) $this->prospect->website_url);

        $this->prospect->update([
            'analysis_status' => 'completed',
            'opportunity_score' => $analysis['score'],
            'findings' => $analysis['findings'],
            'contact_details' => $analysis['contacts'],
            'analysed_at' => now(),
            'status' => 'researched',
        ]);
        $this->prospect->recordActivity('free_audit_completed', 'Free site audit completed and the results email was queued.');

        Mail::to($this->prospect->email)->send(new FreeSiteAuditResults($this->prospect));
    }

    public function failed(?Throwable $exception): void
    {
        $this->prospect->update([
            'analysis_status' => 'failed',
            'analysis_error' => $exception?->getMessage() ?: 'The free site audit could not be completed.',
        ]);
        $this->prospect->recordActivity('free_audit_failed', 'Free site audit could not be completed.');
    }
}

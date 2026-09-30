<?php

namespace App\Jobs;

use App\Models\WebsiteAudit;
use App\Services\MarketingAuditResearch;
use App\Services\ProspectWebsiteAnalyzer;
use App\Support\MarketingJourney;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateWebsiteAudit implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public WebsiteAudit $audit) {}

    public function uniqueId(): string
    {
        return (string) $this->audit->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ProspectWebsiteAnalyzer $analyzer, MarketingJourney $journey, MarketingAuditResearch $research): void
    {
        $this->audit->update([
            'status' => WebsiteAudit::STATUS_RUNNING,
            'analysis_error' => null,
            'started_at' => now(),
        ]);

        $analysis = $analyzer->analyze($this->audit->website_url);
        $insights = $research->forAudit($this->audit->domain, $this->audit->website_url, $analysis);

        $this->audit->update([
            'status' => WebsiteAudit::STATUS_COMPLETED,
            'opportunity_score' => $analysis['score'],
            'findings' => $analysis['findings'],
            'insights' => $insights,
            'contact_details' => $analysis['contacts'],
            'completed_at' => now(),
        ]);

        $journey->record('audit_completed', $this->audit->public_id, $this->audit->marketing_attribution ?? []);
    }

    public function failed(?Throwable $exception): void
    {
        $this->audit->update([
            'status' => WebsiteAudit::STATUS_FAILED,
            'analysis_error' => $exception?->getMessage() ?? 'The website audit could not be completed.',
            'completed_at' => now(),
        ]);
    }
}

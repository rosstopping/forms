<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\InitialProspectOutreachGenerator;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class AnalyzeProspect implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public Prospect $prospect) {}

    /**
     * Execute the job.
     */
    public function handle(ProspectWebsiteAnalyzer $analyzer, ProspectLifecycleManager $lifecycleManager, InitialProspectOutreachGenerator $outreachGenerator): void
    {
        $this->prospect->update(['analysis_status' => 'running', 'analysis_error' => null]);

        try {
            $analysis = $analyzer->analyze($this->prospect->website_url);
            $draft = $outreachGenerator->generate($this->prospect) ?? $this->fallbackDraft();
            $this->prospect->update([
                'analysis_status' => 'completed', 'opportunity_score' => $analysis['score'], 'findings' => $analysis['findings'],
                'contact_details' => $analysis['contacts'], 'email' => $this->prospect->email ?: data_get($analysis, 'contacts.emails.0.value'),
                'analysed_at' => now(), 'outreach_subject' => $draft['subject'], 'outreach_body' => $draft['body'], 'status' => 'drafted',
                'approved_at' => null, 'approved_by' => null, 'scheduled_send_at' => null,
            ]);
            $lifecycleManager->markQualified($this->prospect);
            $this->prospect->recordActivity('analysed', 'Website analysed and an outreach draft prepared.');
        } catch (Throwable $exception) {
            $this->prospect->update(['analysis_status' => 'failed', 'analysis_error' => $exception->getMessage()]);
            $this->prospect->recordActivity('analysis_failed', 'Website analysis could not be completed.');
            throw $exception;
        }
    }

    /** @return array{subject: string, body: string} */
    protected function fallbackDraft(): array
    {
        $contactName = $this->prospect->contact_name;
        $greeting = $contactName ? 'Hi '.$contactName.',' : 'Hi,';

        return [
            'subject' => Str::lower((string) data_get($this->prospect->prospecting_context, 'industry', 'website')),
            'body' => "{$greeting}\n\nI came across {$this->prospect->business_name} earlier and had a look through the website. I noticed a few things that may be worth reviewing.\n\nI’m a web developer, so this is the sort of thing I work on regularly. Happy to send over what I noticed if you’re interested.\n\nCheers,\nRoss",
        ];
    }
}

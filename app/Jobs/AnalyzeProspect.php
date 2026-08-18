<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    public function handle(ProspectWebsiteAnalyzer $analyzer): void
    {
        $this->prospect->update(['analysis_status' => 'running', 'analysis_error' => null]);

        try {
            $analysis = $analyzer->analyze($this->prospect->website_url);
            $draft = $this->draft();
            $this->prospect->update([
                'analysis_status' => 'completed', 'opportunity_score' => $analysis['score'], 'findings' => $analysis['findings'],
                'contact_details' => $analysis['contacts'], 'email' => $this->prospect->email ?: data_get($analysis, 'contacts.emails.0.value'),
                'analysed_at' => now(), 'outreach_subject' => $draft['subject'], 'outreach_body' => $draft['body'], 'status' => 'drafted',
                'approved_at' => null, 'approved_by' => null,
            ]);
            $this->prospect->recordActivity('analysed', 'Website analysed and an outreach draft prepared.');
        } catch (Throwable $exception) {
            $this->prospect->update(['analysis_status' => 'failed', 'analysis_error' => $exception->getMessage()]);
            $this->prospect->recordActivity('analysis_failed', 'Website analysis could not be completed.');
            throw $exception;
        }
    }

    /** @return array{subject: string, body: string} */
    protected function draft(): array
    {
        if (blank($this->prospect->showcase_video_url)) {
            $contactName = $this->prospect->contact_name ?: 'there';

            return [
                'subject' => 'Quick one for '.$this->prospect->business_name,
                'body' => "Hi {$contactName},\n\nI’ll be upfront — this is a cold email. I’m a web developer and I’m trying to pick up a few new clients locally.\n\nI came across {$this->prospect->business_name} and had a look at your website. You’re already appearing in Google, but you’re quite a way down for some searches that could probably be bringing you work.\n\nI manage the whole lot for £149/month — website, hosting, SEO and ongoing improvements.\n\nIf you’d like, I’ll send you a quick video showing what I found on yours and what I’d change. No hard sell afterwards.\n\nCheers,\nRoss",
            ];
        }

        return [
            'subject' => 'Quick one for '.$this->prospect->business_name,
            'body' => "Hi there,\n\nI came across {$this->prospect->business_name} on Google and thought Sitewell might be useful for you.\n\nI ran your website through it and recorded a quick video showing what I found.\n\nNo sales pitch — just thought it might be worth a look.\n\nCheers,\nRoss",
        ];
    }
}

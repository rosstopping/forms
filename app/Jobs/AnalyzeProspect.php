<?php

namespace App\Jobs;

use App\Ai\Agents\ProspectOutreachWriter;
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
    public function handle(ProspectWebsiteAnalyzer $analyzer, ProspectOutreachWriter $writer): void
    {
        $this->prospect->update(['analysis_status' => 'running', 'analysis_error' => null]);

        try {
            $analysis = $analyzer->analyze($this->prospect->website_url);
            $draft = $this->draft($writer, collect($analysis['findings'])->whereIn('severity', ['warning', 'failed'])->take(2)->all());
            $this->prospect->update([
                'analysis_status' => 'completed', 'opportunity_score' => $analysis['score'], 'findings' => $analysis['findings'],
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

    /** @param array<int, array<string, mixed>> $findings
     * @return array{subject: string, body: string}
     */
    protected function draft(ProspectOutreachWriter $writer, array $findings): array
    {
        if ($findings === []) {
            return ['subject' => 'A quick website review for '.$this->prospect->business_name, 'body' => 'Hi '.($this->prospect->contact_name ?: 'there').",\n\nI had a look at your website and it appears to cover the essentials well. I have put together a short review in case it is useful.\n\nThere is no obligation at all—if you would like to talk through any ideas afterwards, just reply to this email.\n\nBest wishes"];
        }

        try {
            $response = $writer->prompt(json_encode(['business' => $this->prospect->business_name, 'contact' => $this->prospect->contact_name, 'website' => $this->prospect->website_url, 'findings' => $findings], JSON_THROW_ON_ERROR));

            return ['subject' => $response['subject'], 'body' => $response['body']];
        } catch (Throwable) {
            $issues = collect($findings)->take(2)->pluck('message')->implode(' ');
            $name = $this->prospect->contact_name ?: 'there';

            return [
                'subject' => 'A quick website review for '.$this->prospect->business_name,
                'body' => "Hi {$name},\n\nI had a look at your website and noticed a couple of things that may be worth reviewing: {$issues}\n\nI have included a short website review below. There is no obligation at all—if you would like to talk through any of it, just reply to this email.\n\nBest wishes",
            ];
        }
    }
}

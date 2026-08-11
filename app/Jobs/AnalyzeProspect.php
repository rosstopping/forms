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
            $draft = $this->draft($writer, $analysis['findings']);
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
            return ['subject' => 'A quick question about '.$this->prospect->business_name, 'body' => 'Hi '.($this->prospect->contact_name ?: 'there').",\n\nI took a look at your website and it appears to cover the essentials well. I help businesses keep their websites healthy and conversion-ready. Would a short, no-obligation review be useful?\n\nBest regards"];
        }

        try {
            $response = $writer->prompt(json_encode(['business' => $this->prospect->business_name, 'contact' => $this->prospect->contact_name, 'website' => $this->prospect->website_url, 'findings' => $findings], JSON_THROW_ON_ERROR));

            return ['subject' => $response['subject'], 'body' => $response['body']];
        } catch (Throwable) {
            $issues = collect($findings)->take(2)->pluck('message')->implode(' ');
            $name = $this->prospect->contact_name ?: 'there';

            return [
                'subject' => 'A quick website observation for '.$this->prospect->business_name,
                'body' => "Hi {$name},\n\nI was looking at your website and noticed a couple of opportunities that may be worth reviewing: {$issues}\n\nI help businesses improve issues like these. Would you like me to send over a short breakdown?\n\nBest regards",
            ];
        }
    }
}

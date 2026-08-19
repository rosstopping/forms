<?php

namespace App\Jobs;

use App\Models\Prospect;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
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
                'approved_at' => null, 'approved_by' => null, 'scheduled_send_at' => null,
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
            $rankingSentence = $this->rankingSentence();

            return [
                'subject' => 'Quick one for '.$this->prospect->business_name,
                'body' => "Hi {$contactName},\n\nI came across {$this->prospect->business_name} while looking at local search results.{$rankingSentence} I spotted a few opportunities that could help the website bring in more local enquiries.\n\nI’ve put together a short website audit showing some of the issues I found. I can also record a quick video explaining what I’d prioritise.\n\nWould you like me to send the video over?\n\nCheers,\nRoss",
            ];
        }

        return [
            'subject' => 'Quick one for '.$this->prospect->business_name,
            'body' => "Hi there,\n\nI came across {$this->prospect->business_name} on Google and thought Sitewell might be useful for you.\n\nI ran your website through it and recorded a quick video showing what I found.\n\nNo sales pitch — just thought it might be worth a look.\n\nCheers,\nRoss",
        ];
    }

    private function rankingSentence(): string
    {
        $activity = $this->prospect->activities()
            ->where('type', 'seo_opportunity_imported')
            ->latest()
            ->first();
        $bestRanking = collect(data_get($activity?->metadata, 'rankings', []))->sortBy('position')->first();

        if (! is_array($bestRanking) || blank(Arr::get($bestRanking, 'keyword')) || ! is_numeric(Arr::get($bestRanking, 'position'))) {
            return '';
        }

        $position = max(1, (int) $bestRanking['position']);
        $page = (int) ceil($position / 10);

        return ' I found your website for “'.$bestRanking['keyword'].'” on page '.$page.' of Google (position '.$position.').';
    }
}

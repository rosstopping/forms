<?php

namespace App\Services;

use App\Jobs\VerifySeoImpact;
use App\Models\ContentGeneration;
use App\Models\RemediationRun;
use App\Models\SeoImpact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeoImpactAutomation
{
    public function __construct(private SeoImpactTracker $tracker) {}

    public function generationMerged(ContentGeneration $generation, array $pullRequest = []): void
    {
        if (! $generation->merged_at) {
            return;
        }
        $this->tracker->forGeneration($generation);
        $body = Str::limit((string) ($pullRequest['body'] ?? ''), 60000, '');
        preg_match_all('~https?://[^\s<>"\)\]`]+~i', $body, $matches);
        $discovered = $this->tracker->websiteUrls($generation->plan->website, array_map(fn ($url) => rtrim($url, '.,;'), $matches[0]));
        foreach (SeoImpact::where('content_generation_id', $generation->id)->get() as $impact) {
            $this->activate($impact, $generation->merged_at, 'Automatically assumed live from merged PR #'.$generation->pull_request_number.'.', ($body !== '' ? Str::limit($body, 3000, '') : 'Merged work: '.$impact->hypothesis), $impact->target_urls ?: $discovered);
        }
    }

    public function remediationMerged(RemediationRun $run): void
    {
        if (! $run->merged_at) {
            return;
        }
        $website = $run->report->website;
        $homepage = $website->primaryDomain() ? 'https://'.$website->primaryDomain()->domain : null;
        $findings = collect($run->findings ?? [])->map(fn ($finding) => [...$finding, 'url' => $finding['url'] ?? $homepage]);
        $urls = $findings->pluck('url')->filter()->flatMap(fn ($url) => $this->tracker->websiteUrls($website, [$url]))->unique()->values();
        $checks = $findings->groupBy('url')->map(fn ($rows) => $rows->pluck('key')->filter()->values()->all())->all();
        foreach (($urls->isEmpty() ? collect([collect()]) : $urls->chunk(5)) as $index => $group) {
            $scope = $group->values()->all();
            $impact = SeoImpact::firstOrCreate(['website_id' => $website->id, 'source_key' => 'remediation:'.$run->id.':'.$index], [
                'remediation_run_id' => $run->id, 'title' => 'Verify audit fixes from PR #'.$run->pull_request_number,
                'hypothesis' => Str::limit($findings->map(fn ($finding) => ($finding['label'] ?? $finding['key'] ?? 'Audit finding').': '.($finding['message'] ?? ''))->implode("\n"), 2000, ''),
                'target_urls' => $scope, 'target_queries' => [], 'evidence' => ['source' => 'health_audit', 'report_id' => $run->website_health_report_id, 'checks' => array_intersect_key($checks, array_flip($scope))],
                'automated' => true,
            ]);
            $this->activate($impact, $run->merged_at, 'Automatically assumed live from merged audit PR #'.$run->pull_request_number.'.', $run->summary ?: $impact->hypothesis, $scope);
        }
    }

    public function activate(SeoImpact $impact, Carbon $liveAt, string $delivery, string $changes, array $urls): void
    {
        DB::transaction(function () use ($impact, $liveAt, $delivery, $changes, $urls): void {
            $locked = SeoImpact::lockForUpdate()->findOrFail($impact->id);
            if ($locked->status !== 'planned' || $locked->live_at) {
                return;
            }
            $urls = $this->tracker->websiteUrls($locked->website, $urls);
            $locked->update([
                'automated' => true, 'live_at' => $liveAt, 'status' => 'measuring', 'target_urls' => $urls,
                'actual_changes' => Str::limit($changes, 3000, ''), 'deployment_evidence' => $delivery,
                'property_url' => $locked->website->searchConsoleConnection?->property_url,
                'verification_status' => $urls === [] ? 'scope_missing' : 'pending',
                'next_verification_at' => $urls === [] ? null : now()->addMinutes(15),
                'next_measurement_at' => $urls === [] ? null : now()->addDays(3),
                'review_available_at' => $urls === [] ? now() : null,
                'measurement_error' => $urls === [] ? 'No owned page URL was found in the brief or pull request. Tracking will resume when a page is identified.' : null,
                'automatic_summary' => 'The change is being tracked automatically. Page checks run after deployment; search results are compared after four and eight weeks.',
            ]);
            if ($urls !== []) {
                VerifySeoImpact::dispatch($locked)->delay(now()->addMinutes(15))->afterCommit();
            }
        });
    }

    public function checkpointUpdates(SeoImpact $impact, array $assessment, array $baseline, array $measurement): array
    {
        $outcome = $assessment['outcome'];
        $before = data_get($baseline, 'target.totals.position');
        $after = data_get($measurement, 'target.totals.position');
        $position = is_numeric($before) && is_numeric($after)
            ? ' Search Console average position moved from '.round($before, 1).' to '.round($after, 1).' (lower is better).'
            : '';
        $decision = match ($outcome) {
            'improved' => 'keep',
            'declined' => 'investigate',
            default => $impact->review_after_days < 56 ? 'wait' : 'inconclusive',
        };
        $next = match ($decision) {
            'keep' => 'Keep the change. '.($impact->review_after_days < 56 ? 'Sitewell will check again at eight weeks.' : 'The scheduled measurement period is complete.'),
            'investigate' => 'Review the affected page and competing changes before proposing a revision. No rollback has been applied.',
            'wait' => 'Allow more time; Sitewell will check again at eight weeks.',
            default => 'No reliable improvement or decline can be established. The measurement window is complete; avoid treating missing evidence as a failure.',
        };

        return [
            'automatic_summary' => 'Week '.intdiv($impact->review_after_days, 7).': '.str_replace('_', ' ', $outcome).'. '.$assessment['reason'].$position.' '.$next,
            'suggested_decision' => $decision, 'review_available_at' => now(), 'acknowledged_at' => null,
            ...($impact->review_after_days >= 56 ? ['status' => 'completed', 'next_measurement_at' => null, 'decision' => $decision, 'decision_notes' => $next, 'reviewed_at' => now()] : []),
        ];
    }
}

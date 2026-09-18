<?php

namespace App\Jobs;

use App\Models\SeoImpact;
use App\Services\SearchConsoleClient;
use App\Services\SeoImpactAutomation;
use App\Services\SeoImpactEvaluator;
use App\Services\SeoImpactTracker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MeasureSeoImpact implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public array $backoff = [60, 300];

    public function __construct(public SeoImpact $impact) {}

    public function uniqueId(): string
    {
        return (string) $this->impact->id;
    }

    public function handle(SearchConsoleClient $search, SeoImpactEvaluator $evaluator): void
    {
        Cache::lock('seo-impact-measure:'.$this->impact->id, 150)->get(function () use ($search, $evaluator): void {
            $impact = $this->impact->fresh(['website.owner', 'website.searchConsoleConnection']);
            if (! $impact || ! in_array($impact->status, ['planned', 'measuring'], true) || $impact->target_urls === [] || ! $impact->website->is_active
                || ! ($impact->website->owner?->hasMembershipFeature('growth') || $impact->website->owner?->isAdmin())) {
                return;
            }
            $connection = $impact->website->searchConsoleConnection;
            if (! $connection?->property_url || ($impact->property_url && $impact->property_url !== $connection->property_url)) {
                $impact->update([...(! $impact->measurement_error ? ['review_available_at' => now(), 'acknowledged_at' => null] : []), 'measurement_error' => 'Connect the original Search Console property before measuring this change.', 'next_measurement_at' => now()->addDay()]);

                return;
            }
            $version = $impact->getAttributes();
            $latest = now('America/Los_Angeles')->subDays(3)->startOfDay();
            $liveDay = $impact->live_at?->copy()->setTimezone('America/Los_Angeles')->startOfDay();
            $baselineEnd = $liveDay ? $liveDay->copy()->subDay() : $latest;
            if ($baselineEnd->greaterThan($latest)) {
                return;
            }
            $baseline = $impact->baseline;
            if (! $baseline || ! $liveDay || ($baseline['end'] ?? null) !== $baselineEnd->toDateString()) {
                $baseline = $this->measure($search, $impact, $baselineEnd->copy()->subDays(27), $baselineEnd);
            }
            $observations = null;
            $review = null;
            if ($liveDay && $latest->greaterThan($liveDay)) {
                $checkpointEnd = $liveDay->copy()->addDays($impact->review_after_days);
                $end = $latest->min($checkpointEnd);
                $start = $end->copy()->subDays(27)->max($liveDay->copy()->addDay());
                $observations = $this->measure($search, $impact, $start, $end);
                if ($end->equalTo($checkpointEnd)) {
                    $review = $evaluator->assess($baseline, $observations, $impact->primary_metric, $this->overlaps($impact, $baseline['start'], $end));
                }
            }
            DB::transaction(function () use ($impact, $version, $connection, $baseline, $observations, $review): void {
                $locked = SeoImpact::lockForUpdate()->findOrFail($impact->id);
                if ($locked->getAttributes() !== $version) {
                    return;
                }
                $updates = ['property_url' => $connection->property_url, 'baseline' => $baseline, 'observations' => $observations,
                    'last_measured_at' => now(), 'next_measurement_at' => $impact->live_at ? ($impact->automated ? $impact->live_at->copy()->setTimezone('America/Los_Angeles')->startOfDay()->addDays($impact->review_after_days + 3)->utc() : now()->addDay()) : null, 'measurement_error' => null];
                if ($review) {
                    $locked->reviews()->firstOrCreate(['checkpoint' => $impact->review_after_days], [
                        'period_start' => $observations['start'], 'period_end' => $observations['end'], 'baseline' => $baseline,
                        'measurement' => $observations, 'assessment' => $review, 'outcome' => $review['outcome'],
                    ]);
                    $updates['outcome'] = $review['outcome'];
                    if ($impact->review_after_days === 28) {
                        $updates['review_after_days'] = 56;
                        if ($impact->automated) {
                            $updates['next_measurement_at'] = $impact->live_at->copy()->setTimezone('America/Los_Angeles')->startOfDay()->addDays(59)->utc();
                        }
                    } else {
                        $updates['status'] = 'review_required';
                        $updates['next_measurement_at'] = null;
                    }
                }
                if ($review && $impact->automated) {
                    $updates = [...$updates, ...app(SeoImpactAutomation::class)->checkpointUpdates($impact, $review, $baseline, $observations)];
                }
                $locked->update($updates);
            });
        });
    }

    /** @return array{start: string, end: string, source: string, complete: bool, target: array, control: ?array} */
    private function measure(SearchConsoleClient $search, SeoImpact $impact, Carbon $start, Carbon $end): array
    {
        $connection = $impact->website->searchConsoleConnection;
        $target = $search->impactPerformance($connection, $start, $end, $impact->target_urls, $impact->target_queries, $impact->country, $impact->device);
        $control = $impact->control_url ? $search->impactPerformance($connection, $start, $end, [$impact->control_url], [], $impact->country, $impact->device) : null;
        if (($target['finalized'] ?? true) === false || ($control['finalized'] ?? true) === false) {
            throw new \RuntimeException('Search Console is still finalising the requested period.');
        }

        return ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'source' => 'search_console', 'complete' => $target['complete'], 'target' => $target, 'control' => $control];
    }

    private function overlaps(SeoImpact $impact, string $start, Carbon $end): bool
    {
        $tracker = app(SeoImpactTracker::class);
        $urls = array_map($tracker->urlKey(...), [...$impact->target_urls, ...($impact->control_url ? [$impact->control_url] : [])]);

        return SeoImpact::where('website_id', $impact->website_id)->whereKeyNot($impact->id)
            ->when($impact->content_generation_id, fn ($query) => $query->where(fn ($query) => $query->whereNull('content_generation_id')->orWhere('content_generation_id', '!=', $impact->content_generation_id)))
            ->whereBetween('live_at', [Carbon::parse($start, 'America/Los_Angeles')->utc(), $end->copy()->endOfDay()->utc()])
            ->get(['target_urls', 'target_queries'])->contains(fn (SeoImpact $other): bool => array_intersect($urls, array_map($tracker->urlKey(...), $other->target_urls)) !== []
                || array_intersect(array_map('mb_strtolower', $impact->target_queries), array_map('mb_strtolower', $other->target_queries)) !== []);
    }

    public function failed(?Throwable $exception): void
    {
        SeoImpact::whereKey($this->impact->id)->whereIn('status', ['planned', 'measuring'])->update([
            'measurement_error' => 'Search Console could not be measured. The next scheduled attempt will retry; missing data is not zero.',
            'next_measurement_at' => now()->addDay(), 'review_available_at' => now(), 'acknowledged_at' => null,
        ]);
    }
}

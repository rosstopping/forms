<?php

namespace App\Jobs;

use App\Models\SeoImpact;
use App\Services\SeoImpactTracker;
use App\Services\WebsiteCrawler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerifySeoImpact implements ShouldBeUnique, ShouldQueue
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

    public function handle(WebsiteCrawler $crawler, SeoImpactTracker $tracker): void
    {
        Cache::lock('seo-impact-verify:'.$this->impact->id, 150)->get(function () use ($crawler, $tracker): void {
            $impact = $this->impact->fresh(['website.owner', 'website.domains']);
            if (! $impact?->live_at || $impact->status === 'cancelled' || ! $impact->website->is_active
                || ! ($impact->website->owner?->hasMembershipFeature('growth') || $impact->website->owner?->isAdmin())) {
                return;
            }
            $version = $impact->getAttributes();
            $pages = [];
            foreach ($tracker->websiteUrls($impact->website, $impact->target_urls) as $url) {
                try {
                    $page = $crawler->inspectPage($url);
                    $checks = collect($page['checks'])->keyBy('key');
                    $expected = data_get($impact->evidence, 'checks', [])[$url] ?? [];
                    $required = array_unique([...$expected, 'page_available', 'indexable']);
                    $unresolved = collect($required)->filter(fn ($key) => data_get($checks->get($key), 'status') !== 'passed')->values()->all();
                    $pages[] = ['url' => $url, 'status_code' => $page['status_code'], 'checks' => $checks->values()->all(), 'expected_checks' => $expected,
                        'unresolved' => $unresolved, 'status' => $unresolved === [] ? ($expected === [] ? 'checked' : 'passed') : 'attention',
                        'note' => $expected === [] ? 'Availability and indexing directives checked. These checks do not verify the wording of the published change.' : 'Rechecked the original audit findings against the live page.'];
                } catch (Throwable $exception) {
                    $pages[] = ['url' => $url, 'status' => 'attention', 'note' => 'The page could not be checked. Sitewell will retry; this is not proof that deployment failed.'];
                }
            }
            $attempt = (int) data_get($impact->verification, 'attempt', 0) + 1;
            $attention = count($pages) !== count($impact->target_urls) || collect($pages)->contains('status', 'attention');
            $status = $pages === [] ? 'scope_missing' : ($attention ? 'attention' : (collect($pages)->every(fn ($page) => $page['status'] === 'passed') ? 'passed' : 'checked'));
            DB::transaction(function () use ($impact, $version, $status, $attempt, $pages, $attention): void {
                $locked = SeoImpact::lockForUpdate()->findOrFail($impact->id);
                if ($locked->getAttributes() !== $version) {
                    return;
                }
                $locked->update([
                    'verification_status' => $status, 'verification' => ['attempt' => $attempt, 'pages' => $pages, 'checked_at' => now()->toIso8601String()],
                    'verified_at' => now(), 'next_verification_at' => $attention && $attempt < 3 ? now()->addDay() : null,
                    ...($attention ? ['review_available_at' => now(), 'acknowledged_at' => null] : []),
                ]);
            });
        });
    }

    public function failed(?Throwable $exception): void
    {
        SeoImpact::whereKey($this->impact->id)->where('status', '!=', 'cancelled')->update([
            'verification_status' => 'attention', 'next_verification_at' => now()->addDay(), 'review_available_at' => now(),
        ]);
    }
}

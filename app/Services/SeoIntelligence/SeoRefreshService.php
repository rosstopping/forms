<?php

namespace App\Services\SeoIntelligence;

use App\Jobs\GenerateSeoIntelligence;
use App\Models\SeoSnapshot;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class SeoRefreshService
{
    public function request(Website $website): SeoRefreshResult
    {
        return Cache::lock('seo-intelligence-refresh:'.$website->id, 10)->block(3, function () use ($website): SeoRefreshResult {
            $activeSnapshot = $website->seoSnapshots()
                ->where('provider', SeoSnapshot::PROVIDER_DATAFORSEO)
                ->whereIn('status', [SeoSnapshot::STATUS_PENDING, SeoSnapshot::STATUS_PROCESSING])
                ->where('created_at', '>=', now()->subMinutes((int) config('services.dataforseo.pending_timeout_minutes')))
                ->latest('id')
                ->first();

            if ($activeSnapshot) {
                return new SeoRefreshResult($activeSnapshot, false, SeoRefreshResult::REASON_IN_PROGRESS);
            }

            $freshSnapshot = $website->seoSnapshots()
                ->where('provider', SeoSnapshot::PROVIDER_DATAFORSEO)
                ->whereIn('status', [SeoSnapshot::STATUS_COMPLETED, SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS])
                ->where('completed_at', '>=', now()->subDays((int) config('services.dataforseo.refresh_days')))
                ->latest('completed_at')
                ->first();

            if ($freshSnapshot) {
                return new SeoRefreshResult($freshSnapshot, false, SeoRefreshResult::REASON_FRESH);
            }

            $domain = $website->primaryDomain()?->domain;

            if (! is_string($domain) || $domain === '') {
                throw new InvalidArgumentException('The website does not have a domain to analyse.');
            }

            $snapshot = $website->seoSnapshots()->create([
                'provider' => SeoSnapshot::PROVIDER_DATAFORSEO,
                'domain' => $domain,
                'location_code' => (int) config('services.dataforseo.location_code'),
                'language_code' => (string) config('services.dataforseo.language_code'),
                'status' => SeoSnapshot::STATUS_PENDING,
                'snapshot_date' => today(),
                'metadata' => ['data_source' => 'third_party_estimate'],
                'errors' => [],
            ]);

            GenerateSeoIntelligence::dispatch($snapshot);

            return new SeoRefreshResult($snapshot, true, SeoRefreshResult::REASON_QUEUED);
        });
    }
}

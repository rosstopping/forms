<?php

namespace App\Services\SeoIntelligence;

use App\Models\SeoSnapshot;
use App\Models\Website;
use App\Services\DataForSEO\DataForSEOClient;
use Illuminate\Support\Carbon;

class SeoHistoryService
{
    public function __construct(protected DataForSEOClient $dataForSeo) {}

    public function backfill(Website $website): int
    {
        $domain = $website->primaryDomain()?->domain;
        if (! $domain) {
            return 0;
        }

        $response = $this->dataForSeo->post('dataforseo_labs/google/historical_rank_overview/live', [
            'target' => $domain,
            'location_code' => (int) config('services.dataforseo.location_code'),
            'language_code' => (string) config('services.dataforseo.language_code'),
            'date_from' => '2020-10-01',
            'date_to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'correlate' => true,
        ]);

        $items = data_get($response->results, '0.items', []);
        $stored = 0;
        foreach (is_array($items) ? $items : [] as $item) {
            $organic = data_get($item, 'metrics.organic');
            if (! is_array($organic) || ! is_numeric($item['year'] ?? null) || ! is_numeric($item['month'] ?? null)) {
                continue;
            }
            $date = Carbon::create((int) $item['year'], (int) $item['month'], 1)->endOfMonth();
            $top3 = (int) ($organic['pos_1'] ?? 0) + (int) ($organic['pos_2_3'] ?? 0);
            $top10 = $top3 + (int) ($organic['pos_4_10'] ?? 0);
            $top20 = $top10 + (int) ($organic['pos_11_20'] ?? 0);
            $website->seoSnapshots()->updateOrCreate(
                ['provider' => SeoSnapshot::PROVIDER_DATAFORSEO, 'snapshot_date' => $date->toDateString()],
                ['domain' => $domain, 'location_code' => config('services.dataforseo.location_code'), 'language_code' => config('services.dataforseo.language_code'), 'status' => SeoSnapshot::STATUS_COMPLETED, 'organic_keywords' => (int) ($organic['count'] ?? 0), 'estimated_organic_traffic' => (float) ($organic['etv'] ?? 0), 'top_3_keywords' => $top3, 'top_10_keywords' => $top10, 'top_20_keywords' => $top20, 'top_100_keywords' => (int) ($organic['count'] ?? 0), 'metadata' => ['data_source' => 'third_party_estimate', 'historical' => true, 'is_new' => (int) ($organic['is_new'] ?? 0), 'is_up' => (int) ($organic['is_up'] ?? 0), 'is_down' => (int) ($organic['is_down'] ?? 0), 'is_lost' => (int) ($organic['is_lost'] ?? 0)], 'errors' => [], 'started_at' => $date, 'completed_at' => $date],
            );
            $stored++;
        }
        $website->update(['seo_history_backfilled_at' => now()]);

        return $stored;
    }
}

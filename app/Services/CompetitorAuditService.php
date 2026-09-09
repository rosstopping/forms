<?php

namespace App\Services;

use App\Jobs\ProcessCompetitorAuditStage;
use App\Models\CompetitorAudit;
use App\Models\ExternalApiUsage;
use App\Models\WebsiteCompetitor;
use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\DomainIntersectionService;
use App\Services\DataForSEO\RankedKeywordsService;
use App\Services\DataForSEO\RelevantPagesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompetitorAuditService
{
    public const STAGES = ['ranked_top', 'ranked_deep', 'shared_keywords', 'missing_keywords', 'leading_pages', 'pages', 'briefs'];

    public function request(WebsiteCompetitor $competitor): CompetitorAudit
    {
        return Cache::lock('competitor-audit-request:'.$competitor->id, 10)->block(3, function () use ($competitor): CompetitorAudit {
            $competitor->refresh();
            if ($competitor->excluded) {
                throw ValidationException::withMessages(['competitor' => 'Restore this competitor before auditing it.']);
            }
            $domain = $competitor->website->primaryDomain()?->domain;
            if (! $domain) {
                throw ValidationException::withMessages(['competitor' => 'Add a website domain before auditing competitors.']);
            }
            $domain = app(CompetitorDomain::class)->normalize($domain);
            if ($domain === $competitor->domain) {
                throw ValidationException::withMessages(['competitor' => 'Choose a domain other than your own website.']);
            }
            $match = $competitor->audits()->where('domain', $domain)->where('competitor_domain', $competitor->domain)
                ->where('provider', 'dataforseo')->where('location_code', config('services.dataforseo.location_code'))
                ->where('language_code', config('services.dataforseo.language_code'));
            $audit = (clone $match)->latest('id')->first();
            if ($audit && in_array($audit->status, ['pending', 'processing'], true) && $audit->updated_at->gt(now()->subMinutes(30))) {
                return $audit;
            }
            if ($audit && in_array($audit->status, ['completed', 'completed_with_errors'], true) && $audit->completed_at?->gt(now()->subDays(7))) {
                return $audit;
            }
            if (! $audit || $audit->created_at->lt(now()->subDays(7)) || in_array($audit->status, ['completed', 'completed_with_errors'], true)) {
                $audit = $competitor->audits()->create([
                    'website_id' => $competitor->website_id, 'domain' => $domain, 'competitor_domain' => $competitor->domain,
                    'location_code' => config('services.dataforseo.location_code'), 'language_code' => config('services.dataforseo.language_code'),
                    'limits' => config('services.dataforseo.competitor_audits'), 'stages' => [], 'errors' => [],
                ]);
            }
            $audit->update(['status' => 'pending', 'completed_at' => null]);
            ProcessCompetitorAuditStage::dispatch($audit, $this->nextStage($audit) ?? 'briefs')->afterCommit();

            return $audit;
        });
    }

    public function nextStage(CompetitorAudit $audit): ?string
    {
        foreach (self::STAGES as $stage) {
            if (! in_array($stage, $audit->stages ?? [], true)) {
                return $stage;
            }
        }

        return null;
    }

    public function process(CompetitorAudit $audit, string $stage): void
    {
        if (in_array($stage, $audit->stages ?? [], true)) {
            return;
        }
        $audit->update(['status' => 'processing', 'started_at' => $audit->started_at ?? now()]);
        if (str_starts_with($stage, 'ranked_')) {
            $top = $stage === 'ranked_top';
            $limit = (int) $audit->limits['ranked_keywords'];
            $response = app(RankedKeywordsService::class)->forRange($audit->competitor_domain, $audit->location_code, $audit->language_code, $top ? 1 : 11, $top ? 10 : 100, $top ? (int) floor($limit * .4) : (int) ceil($limit * .6));
            DB::transaction(function () use ($audit, $stage, $response): void {
                foreach ($response->keywords as $keyword) {
                    $this->saveKeyword($audit, $keyword, 'unknown');
                }
                $this->usage($audit, $stage, $response->endpoint, $response->cost, $response->resultCount, $response->taskId);
                $this->completeStage($audit, $stage);
            });

            return;
        }
        if (in_array($stage, ['shared_keywords', 'missing_keywords', 'leading_pages'], true)) {
            $response = $stage === 'leading_pages'
                ? app(RelevantPagesService::class)->forDomain($audit->competitor_domain, $audit->location_code, $audit->language_code, $audit->limits[$stage])
                : app(DomainIntersectionService::class)->compare($audit->competitor_domain, $audit->domain, $audit->location_code, $audit->language_code, $stage === 'shared_keywords', $audit->limits[$stage]);
            $items = data_get($response->results, '0.items');
            $this->usage($audit, $stage, $response->endpoint, $response->cost, is_array($items) ? count($items) : 0, $response->taskId);
            if (! is_array($items) && ! (is_null($items) && data_get($response->results, '0.total_count') === 0)) {
                throw new RuntimeException('The provider returned an invalid dataset.');
            }
            DB::transaction(function () use ($audit, $stage, $items): void {
                foreach ($items ?? [] as $item) {
                    if (! is_array($item)) {
                        throw new RuntimeException('The provider returned an invalid row.');
                    }
                    if ($stage === 'leading_pages') {
                        $url = $item['page_address'] ?? null;
                        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                            throw new RuntimeException('The provider returned an invalid page.');
                        }
                        $audit->pages()->updateOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url, 'estimated_traffic' => data_get($item, 'metrics.organic.etv'), 'organic_keywords' => data_get($item, 'metrics.organic.count')]);
                    } else {
                        $mapped = ['keyword_data' => $item['keyword_data'] ?? [], 'ranked_serp_element' => ['serp_item' => $item['first_domain_serp_element'] ?? []]];
                        $mapped['keyword_data']['location_code'] = $audit->location_code;
                        $mapped['keyword_data']['language_code'] = $audit->language_code;
                        $keyword = RankedKeywordData::fromArray($mapped);
                        if (! $keyword || $keyword->position < 1) {
                            throw new RuntimeException('The provider returned an invalid keyword.');
                        }
                        $ours = data_get($item, 'second_domain_serp_element.rank_group');
                        if ($stage === 'shared_keywords' && (! is_numeric($ours) || $ours < 1)) {
                            throw new RuntimeException('The provider did not supply a shared ranking.');
                        }
                        $this->saveKeyword($audit, $keyword, $stage === 'missing_keywords' ? 'missing' : 'shared', $stage === 'shared_keywords' ? (int) $ours : null, data_get($item, 'second_domain_serp_element.url'));
                    }
                }
                $this->completeStage($audit, $stage);
            });

            return;
        }
        if ($stage === 'pages') {
            $siteTerms = Str::lower($audit->website->name.' '.($audit->website->contentPlan?->audience ?? '').' '.($audit->website->contentPlan?->guidance ?? ''));
            $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', $siteTerms) ?: [])->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique();
            $urls = $audit->keywords()->whereIn('comparison', ['missing', 'shared'])->where('search_intent', '!=', 'navigational')->get()
                ->sortByDesc(fn ($keyword): float => ($terms->contains(fn (string $term): bool => Str::contains(Str::lower($keyword->keyword), $term)) ? 1e12 : 0) + ($keyword->search_volume ?? 0))
                ->pluck('ranking_url')->filter()->unique()->take($audit->limits['analyse_pages']);
            foreach ($urls as $url) {
                $audit->pages()->firstOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url]);
            }
            $pages = $audit->pages()->get()->sortByDesc(fn ($page): float => ($urls->contains($page->url) ? 1e15 : 0) + (float) $page->estimated_traffic)->take($audit->limits['analyse_pages']);
            foreach ($pages as $page) {
                if ($page->fetched_at) {
                    continue;
                }
                try {
                    $analysis = app(CompetitorPageFetcher::class)->fetch($page->url, $audit->competitor_domain);
                    $page->update(['status' => 'completed', 'analysis' => $analysis, 'fetched_at' => now(), 'error' => null]);
                } catch (\Throwable $exception) {
                    report($exception);
                    $page->update(['status' => 'unavailable', 'error' => 'This page could not be safely fetched or analysed.', 'fetched_at' => now()]);
                }
            }
        } elseif ($stage === 'briefs') {
            app(CompetitorBriefGenerator::class)->generate($audit);
        } else {
            throw new RuntimeException('Unknown competitor audit stage.');
        }
        $this->completeStage($audit, $stage);
    }

    private function saveKeyword(CompetitorAudit $audit, RankedKeywordData $keyword, string $comparison, ?int $ourPosition = null, ?string $ourUrl = null): void
    {
        $audit->keywords()->updateOrCreate(['fingerprint' => hash('sha256', Str::lower(trim($keyword->keyword)))], [
            'keyword' => Str::limit($keyword->keyword, 255, ''), 'position' => $keyword->position, 'ranking_url' => $keyword->rankingUrl,
            'search_volume' => $keyword->searchVolume, 'search_intent' => $keyword->searchIntent, 'keyword_difficulty' => $keyword->keywordDifficulty,
            'estimated_traffic' => $keyword->estimatedTraffic, 'comparison' => $comparison, 'our_position' => $ourPosition, 'our_ranking_url' => $ourUrl,
        ]);
    }

    private function usage(CompetitorAudit $audit, string $stage, string $endpoint, float $cost, int $count, ?string $task): void
    {
        ExternalApiUsage::create(['website_id' => $audit->website_id, 'competitor_audit_id' => $audit->id, 'provider' => 'dataforseo', 'endpoint' => $endpoint, 'request_type' => 'competitor_'.$stage, 'cost' => $cost, 'result_count' => $count, 'provider_task_id' => $task, 'requested_at' => now(), 'metadata' => []]);
    }

    private function completeStage(CompetitorAudit $audit, string $stage): void
    {
        $errors = $audit->errors ?? [];
        unset($errors[$stage]);
        $audit->update(['stages' => array_values(array_unique([...($audit->stages ?? []), $stage])), 'errors' => $errors]);
    }
}

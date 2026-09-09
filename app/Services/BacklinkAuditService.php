<?php

namespace App\Services;

use App\Jobs\ProcessBacklinkAuditStage;
use App\Models\BacklinkAudit;
use App\Models\ExternalApiUsage;
use App\Models\Website;
use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\Data\DataForSEOResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BacklinkAuditService
{
    public const STAGES = ['overview', 'current_links', 'lost_links', 'own_pages', 'trend', 'gaps', 'competitor_pages', 'analyse_pages', 'opportunities'];

    /** @param array<int, int> $competitorIds */
    public function request(Website $website, array $competitorIds): BacklinkAudit
    {
        return Cache::lock('backlink-audit-request:'.$website->id, 10)->block(3, function () use ($website, $competitorIds): BacklinkAudit {
            $primaryDomain = $website->primaryDomain()?->domain;
            if (! $primaryDomain) {
                throw ValidationException::withMessages(['website' => 'Add a website domain before auditing backlinks.']);
            }
            $domain = app(CompetitorDomain::class)->normalize($primaryDomain);
            $competitors = $website->competitors()->whereIn('id', array_unique($competitorIds))->where('excluded', false)->orderBy('domain')->get();
            if ($competitors->count() !== count(array_unique($competitorIds)) || $competitors->count() > 3) {
                throw ValidationException::withMessages(['competitor_ids' => 'Choose up to three active competitors belonging to this website.']);
            }
            $signature = $competitors->pluck('domain')->sort()->implode('|');
            $matching = $website->backlinkAudits()->where('domain', $domain)->where('provider', 'dataforseo')->latest('id')->get()
                ->first(fn (BacklinkAudit $audit): bool => $audit->competitors()->orderBy('domain')->pluck('domain')->implode('|') === $signature);
            if ($matching && in_array($matching->status, ['pending', 'processing'], true) && $matching->updated_at->gt(now()->subMinutes(30))) {
                return $matching;
            }
            if ($matching && in_array($matching->status, ['completed', 'completed_with_errors'], true) && $matching->completed_at?->gt(now()->subDays(7))) {
                return $matching;
            }
            $audit = $matching && $matching->created_at->gte(now()->subDays(7)) && $matching->status === 'failed'
                ? $matching
                : $website->backlinkAudits()->create(['domain' => $domain, 'limits' => config('services.dataforseo.backlink_audits'), 'stages' => [], 'errors' => []]);
            if ($audit->competitors()->doesntExist()) {
                foreach ($competitors as $competitor) {
                    $audit->competitors()->create(['website_competitor_id' => $competitor->id, 'domain' => $competitor->domain]);
                }
            }
            $audit->update(['status' => 'pending', 'completed_at' => null]);
            ProcessBacklinkAuditStage::dispatch($audit, $this->nextStage($audit) ?? 'opportunities')->afterCommit();

            return $audit;
        });
    }

    public function nextStage(BacklinkAudit $audit): ?string
    {
        return collect(self::STAGES)->first(fn (string $stage): bool => ! in_array($stage, $audit->stages ?? [], true));
    }

    public function process(BacklinkAudit $audit, string $stage): void
    {
        if (in_array($stage, $audit->stages ?? [], true)) {
            return;
        }
        $audit->update(['status' => 'processing', 'started_at' => $audit->started_at ?? now()]);
        $provider = app(BacklinksService::class);

        match ($stage) {
            'overview' => $this->overview($audit, $provider),
            'current_links' => $this->links($audit, $provider->backlinks($audit->domain, 'live', $audit->limits['current_links']), 'live', $stage),
            'lost_links' => $this->links($audit, $provider->backlinks($audit->domain, 'lost', $audit->limits['lost_links']), 'lost', $stage),
            'own_pages' => $this->pages($audit, $provider->domainPages($audit->domain, $audit->limits['linked_pages']), $audit->domain, 'own', $stage),
            'trend' => $this->trend($audit, $provider->newLostTrend($audit->domain, $audit->limits['trend_months']), $stage),
            'gaps' => $this->gaps($audit, $provider, $stage),
            'competitor_pages' => $this->competitorPages($audit, $provider, $stage),
            'analyse_pages' => $this->analysePages($audit),
            'opportunities' => app(BacklinkOpportunityGenerator::class)->generate($audit),
            default => throw new RuntimeException('Unknown backlink audit stage.'),
        };
        $this->complete($audit, $stage);
    }

    private function overview(BacklinkAudit $audit, BacklinksService $provider): void
    {
        $response = $provider->overview($audit->domain);
        $data = $response->overview;
        $audit->update(['overview' => ['backlinks' => $data->backlinks, 'referring_domains' => $data->referringDomains, 'referring_ips' => $data->referringIps, 'referring_subnets' => $data->referringSubnets, 'broken_backlinks' => $data->brokenBacklinks, 'domain_rank' => $data->domainRank, 'dofollow' => $data->dofollow, 'nofollow' => $data->nofollow, 'spam_score' => $data->spamScore]]);
        $this->usage($audit, 'overview', $response->endpoint, $response->cost, $response->resultCount, $response->taskId);
    }

    private function links(BacklinkAudit $audit, DataForSEOResponse $response, string $state, string $stage): void
    {
        $items = $this->items($response);
        $this->usage($audit, $stage, $response->endpoint, $response->cost, count($items), $response->taskId);
        DB::transaction(function () use ($audit, $state, $items): void {
            foreach ($items as $item) {
                $sourceUrl = $item['url_from'] ?? null;
                $targetUrl = $item['url_to'] ?? null;
                $sourceDomain = is_string($sourceUrl) ? $this->httpUrlHost($sourceUrl) : null;
                if (! is_string($sourceUrl) || ! $sourceDomain || ! is_string($targetUrl) || ! $this->urlBelongsToDomain($targetUrl, $audit->domain)) {
                    throw new RuntimeException('The provider returned an invalid backlink row.');
                }
                $audit->links()->updateOrCreate(['fingerprint' => hash('sha256', mb_strtolower($sourceUrl).'|'.$targetUrl.'|'.($item['anchor'] ?? ''))], [
                    'state' => $state, 'source_domain' => $sourceDomain, 'source_url' => $sourceUrl, 'target_url' => $targetUrl,
                    'anchor' => is_string($item['anchor'] ?? null) ? $item['anchor'] : null, 'dofollow' => is_bool($item['dofollow'] ?? null) ? $item['dofollow'] : null,
                    'broken' => (bool) ($item['is_broken'] ?? false), 'source_domain_rank' => $this->integer($item['domain_from_rank'] ?? null),
                    'source_page_rank' => $this->integer($item['page_from_rank'] ?? $item['rank'] ?? null), 'spam_score' => $this->integer($item['backlink_spam_score'] ?? null),
                    'links_count' => max(1, $this->integer($item['links_count'] ?? 1) ?? 1), 'semantic_location' => is_string($item['semantic_location'] ?? null) ? $item['semantic_location'] : null,
                    'first_seen' => $this->date($item['first_seen'] ?? null), 'last_seen' => $this->date($item['last_seen'] ?? null),
                ]);
            }
            if ($state === 'live') {
                $counts = $audit->links()->where('state', 'live')->selectRaw('source_domain, SUM(links_count) as total')->groupBy('source_domain')->pluck('total');
                $total = max(1, (int) $counts->sum());
                $audit->update(['overview' => [...($audit->overview ?? []), 'sample_referring_domains' => $counts->count(), 'largest_domain_share' => round(((int) $counts->max() / $total) * 100, 1)]]);
            }
        });
    }

    private function pages(BacklinkAudit $audit, DataForSEOResponse $response, string $domain, string $kind, string $stage): void
    {
        $items = $this->items($response);
        $this->usage($audit, $stage.':'.$domain, $response->endpoint, $response->cost, count($items), $response->taskId);
        DB::transaction(function () use ($audit, $domain, $kind, $items): void {
            foreach ($items as $item) {
                $url = $item['page'] ?? $item['url'] ?? null;
                if (! is_string($url) || ! $this->urlBelongsToDomain($url, $domain)) {
                    throw new RuntimeException('The provider returned an invalid linked page.');
                }
                $summary = is_array($item['page_summary'] ?? null) ? $item['page_summary'] : $item;
                $audit->pages()->updateOrCreate(['url_hash' => hash('sha256', $url)], ['domain' => $domain, 'kind' => $kind, 'url' => $url, 'backlinks' => max(0, $this->integer($summary['backlinks'] ?? null) ?? 0), 'referring_domains' => max(0, $this->integer($summary['referring_domains'] ?? null) ?? 0), 'page_rank' => $this->integer($summary['rank'] ?? $item['rank'] ?? null)]);
            }
        });
    }

    private function trend(BacklinkAudit $audit, DataForSEOResponse $response, string $stage): void
    {
        $items = $this->items($response);
        $this->usage($audit, $stage, $response->endpoint, $response->cost, count($items), $response->taskId);
        $audit->update(['new_lost_trend' => collect($items)->map(function (array $item): array {
            $date = $this->date($item['date'] ?? null);
            if (! $date) {
                throw new RuntimeException('The provider returned an invalid backlink trend row.');
            }

            return ['date' => $date->startOfMonth()->toDateString(), 'new_backlinks' => max(0, $this->integer($item['new_backlinks'] ?? null) ?? 0), 'lost_backlinks' => max(0, $this->integer($item['lost_backlinks'] ?? null) ?? 0), 'new_referring_domains' => max(0, $this->integer($item['new_referring_domains'] ?? null) ?? 0), 'lost_referring_domains' => max(0, $this->integer($item['lost_referring_domains'] ?? null) ?? 0)];
        })->all()]);
    }

    private function gaps(BacklinkAudit $audit, BacklinksService $provider, string $stage): void
    {
        $domains = $audit->competitors()->pluck('domain')->all();
        if ($domains === []) {
            return;
        }
        $response = $provider->domainIntersection($audit->domain, $domains, $audit->limits['gap_domains']);
        $items = $this->items($response);
        $this->usage($audit, $stage, $response->endpoint, $response->cost, count($items), $response->taskId);
        DB::transaction(function () use ($audit, $domains, $items): void {
            foreach ($items as $item) {
                $domain = $item['domain'] ?? $item['target'] ?? null;
                if (! is_string($domain) || $domain === '') {
                    throw new RuntimeException('The provider returned an invalid referring-domain gap.');
                }
                try {
                    $domain = app(CompetitorDomain::class)->normalize($domain);
                } catch (Throwable) {
                    throw new RuntimeException('The provider returned an invalid referring-domain gap.');
                }
                if ($domain === $audit->domain || in_array($domain, $domains, true)) {
                    continue;
                }
                $rawEvidence = is_array($item['domain_intersection'] ?? null) ? $item['domain_intersection'] : [];
                $evidence = collect($rawEvidence)->mapWithKeys(function (mixed $value, string|int $key) use ($domains): array {
                    $domain = $domains[max(0, (int) $key - 1)] ?? (string) $key;

                    return [$domain => is_array($value) ? $value : []];
                })->all();
                $values = collect($evidence)->filter(fn (mixed $value): bool => is_array($value));
                $count = $values->filter(fn (array $value): bool => (int) ($value['backlinks'] ?? $value['referring_pages'] ?? 0) > 0)->count();
                $rank = $this->integer($item['rank'] ?? $values->max('rank'));
                $spam = $this->integer($item['backlink_spam_score'] ?? null);
                $audit->domainGaps()->updateOrCreate(['domain' => mb_strtolower($domain)], ['domain_rank' => $rank, 'spam_score' => $spam, 'competitor_count' => max(1, $count), 'competitor_evidence' => $evidence, 'priority_score' => max(0, min(500, max(1, $count) * 100 + ($rank ?? 0) - ($spam ?? 0)))]);
            }
        });
    }

    private function competitorPages(BacklinkAudit $audit, BacklinksService $provider, string $stage): void
    {
        foreach ($audit->competitors()->pluck('domain') as $domain) {
            $this->pages($audit, $provider->domainPages($domain, $audit->limits['competitor_pages']), $domain, 'competitor', $stage);
        }
    }

    private function analysePages(BacklinkAudit $audit): void
    {
        $siteTerms = Str::lower($audit->website->name.' '.($audit->website->contentPlan?->audience ?? '').' '.($audit->website->contentPlan?->guidance ?? ''));
        $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', $siteTerms) ?: [])->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique();
        $pages = $audit->pages()->where('kind', 'competitor')->orderByDesc('referring_domains')->limit(100)->get()
            ->sortByDesc(fn ($page): int => ($terms->contains(fn (string $term): bool => Str::contains(Str::lower($page->url), $term)) ? 1000000 : 0) + ($page->referring_domains * 100) + min($page->backlinks, 99))
            ->take($audit->limits['analyse_pages']);
        foreach ($pages as $page) {
            if ($page->fetched_at) {
                continue;
            }
            try {
                $page->update(['status' => 'completed', 'analysis' => app(CompetitorPageFetcher::class)->fetch($page->url, $page->domain), 'fetched_at' => now(), 'error' => null]);
            } catch (Throwable $exception) {
                report($exception);
                $page->update(['status' => 'unavailable', 'error' => 'This page could not be safely fetched or analysed.', 'fetched_at' => now()]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function items(DataForSEOResponse $response): array
    {
        $items = data_get($response->results, '0.items');
        if (! is_array($items) && ! (is_null($items) && (int) data_get($response->results, '0.total_count', 0) === 0)) {
            throw new RuntimeException('The provider returned an invalid dataset.');
        }

        return collect($items ?? [])->map(fn (mixed $item): array => is_array($item) ? $item : throw new RuntimeException('The provider returned an invalid row.'))->all();
    }

    private function usage(BacklinkAudit $audit, string $stage, string $endpoint, float $cost, int $count, ?string $taskId): void
    {
        ExternalApiUsage::firstOrCreate(['backlink_audit_id' => $audit->id, 'provider_task_id' => $taskId, 'endpoint' => $endpoint, 'request_type' => 'backlink_'.$stage], ['website_id' => $audit->website_id, 'provider' => 'dataforseo', 'cost' => $cost, 'result_count' => $count, 'requested_at' => now(), 'metadata' => []]);
    }

    private function complete(BacklinkAudit $audit, string $stage): void
    {
        $errors = $audit->errors ?? [];
        unset($errors[$stage]);
        $audit->update(['stages' => array_values(array_unique([...($audit->stages ?? []), $stage])), 'errors' => $errors]);
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function httpUrlHost(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = mb_strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));

        return in_array($scheme, ['http', 'https'], true) && $host !== '' ? preg_replace('/^www\./', '', $host) : null;
    }

    private function urlBelongsToDomain(string $url, string $domain): bool
    {
        $host = $this->httpUrlHost($url);

        return $host === $domain || ($host !== null && str_ends_with($host, '.'.$domain));
    }
}

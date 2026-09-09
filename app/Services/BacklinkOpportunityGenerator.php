<?php

namespace App\Services;

use App\Ai\Agents\BacklinkAnalyst;
use App\Models\BacklinkAudit;
use App\Models\ContentRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BacklinkOpportunityGenerator
{
    public function generate(BacklinkAudit $audit): void
    {
        $this->recoveryOpportunities($audit);
        $this->linkedPageOpportunities($audit);
        $pages = $audit->pages()->where('kind', 'competitor')->where('status', 'completed')->orderByDesc('referring_domains')->limit(20)->get()->keyBy('id');
        if ($pages->isEmpty()) {
            return;
        }
        $website = $audit->website;
        $ownPages = $website->healthReports()->where('status', 'completed')->latest('completed_at')->first()?->pages()->limit(60)->get(['url', 'title', 'meta_description']) ?? collect();
        $context = ['website' => $website->name, 'domain' => $audit->domain, 'audience' => Str::limit((string) $website->contentPlan?->audience, 3000, ''), 'guidance' => Str::limit((string) $website->contentPlan?->guidance, 5000, ''), 'own_pages' => $ownPages->toArray(), 'competitor_pages' => $pages->map(fn ($page): array => ['id' => $page->id, 'url' => $page->url, 'domain' => $page->domain, 'backlinks' => $page->backlinks, 'referring_domains' => $page->referring_domains, 'analysis' => $page->analysis])->values()->all(), 'queued_briefs' => ContentRequest::where('website_id', $website->id)->whereNotNull('backlink_fingerprint')->latest()->limit(20)->pluck('instructions')->all()];
        $response = (new BacklinkAnalyst)->prompt(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), timeout: 120);
        $items = $this->normalize($response['opportunities'] ?? []);
        Validator::make(['opportunities' => $items], [
            'opportunities' => ['array', 'max:3'], 'opportunities.*' => ['array'], 'opportunities.*.title' => ['required', 'string', 'max:200'],
            'opportunities.*.page_ids' => ['present', 'array', 'min:1', 'max:5'], 'opportunities.*.page_ids.*' => ['integer'],
            'opportunities.*.user_need' => ['required', 'string', 'max:500'], 'opportunities.*.relevance_reason' => ['required', 'string', 'max:500'],
            'opportunities.*.existing_page_url' => ['present', 'nullable', 'string'], 'opportunities.*.content_format' => ['required', 'string', 'max:100'],
            'opportunities.*.observations' => ['present', 'array', 'max:5'], 'opportunities.*.hypotheses' => ['present', 'array', 'max:3'],
            'opportunities.*.improvements' => ['required', 'array', 'max:5'], 'opportunities.*.outline' => ['required', 'array', 'max:8'],
            'opportunities.*.internal_links' => ['present', 'array', 'max:5'],
            'opportunities.*.observations.*' => ['string', 'max:500'], 'opportunities.*.hypotheses.*' => ['string', 'max:500'],
            'opportunities.*.improvements.*' => ['string', 'max:500'], 'opportunities.*.outline.*' => ['string', 'max:250'],
            'opportunities.*.internal_links.*' => ['string', 'max:500'],
        ])->validate();
        $ownUrls = $ownPages->pluck('url');
        foreach ($items as $item) {
            if (collect($item['page_ids'])->contains(fn (int $id): bool => ! $pages->has($id)) || ($item['existing_page_url'] && ! $ownUrls->contains($item['existing_page_url']))) {
                continue;
            }
            $selected = $pages->only($item['page_ids']);
            $fingerprint = hash('sha256', 'content|'.Str::lower(trim($item['title'])).'|'.$selected->pluck('domain')->sort()->implode('|'));
            $evidence = [...$item, 'source_urls' => $selected->pluck('url')->values()->all(), 'competitor_domains' => $selected->pluck('domain')->unique()->values()->all(), 'data_source' => 'dataforseo_estimate', 'collected_at' => $audit->started_at?->toIso8601String(), 'audit_id' => $audit->id];
            $audit->opportunities()->firstOrCreate(['fingerprint' => $fingerprint], ['website_id' => $audit->website_id, 'type' => 'content', 'title' => $item['title'], 'priority_score' => 300 + min(100, $selected->sum('referring_domains')), 'evidence' => $evidence]);
        }
    }

    private function recoveryOpportunities(BacklinkAudit $audit): void
    {
        $audit->links()->where(fn ($query) => $query->where('state', 'lost')->orWhere('broken', true))->where(fn ($query) => $query->whereNull('spam_score')->orWhere('spam_score', '<=', 70))->where(fn ($query) => $query->whereNull('source_domain_rank')->orWhere('source_domain_rank', '>=', 10))->orderByDesc('source_domain_rank')->limit(20)->get()->each(function ($link) use ($audit): void {
            $type = $link->broken ? 'broken_link' : 'lost_link';
            $fingerprint = hash('sha256', $type.'|'.$link->source_domain.'|'.$link->target_url);
            $audit->opportunities()->firstOrCreate(['fingerprint' => $fingerprint], ['website_id' => $audit->website_id, 'type' => $type, 'title' => ($link->broken ? 'Repair' : 'Recover').' a link from '.$link->source_domain, 'priority_score' => 200 + ($link->source_domain_rank ?? 0) + ($link->dofollow ? 25 : 0), 'evidence' => ['observations' => [($link->broken ? 'The provider marks this backlink as broken.' : 'The provider no longer observed this backlink as live.')], 'hypotheses' => ['Restoring a relevant editorial link may recover referral and discovery value.'], 'source_url' => $link->source_url, 'target_url' => $link->target_url, 'anchor' => $link->anchor, 'source_domain_rank' => $link->source_domain_rank, 'dofollow' => $link->dofollow, 'data_source' => 'dataforseo_observation', 'collected_at' => $audit->started_at?->toIso8601String(), 'audit_id' => $audit->id]]);
        });
    }

    private function linkedPageOpportunities(BacklinkAudit $audit): void
    {
        $audit->pages()->where('kind', 'own')->where('referring_domains', '>', 0)->orderByDesc('referring_domains')->limit(5)->get()->each(function ($page) use ($audit): void {
            $fingerprint = hash('sha256', 'linked_page|'.$page->url);
            $audit->opportunities()->firstOrCreate(['fingerprint' => $fingerprint], ['website_id' => $audit->website_id, 'type' => 'linked_page', 'title' => 'Protect and strengthen a linked page', 'priority_score' => 150 + min(100, $page->referring_domains), 'evidence' => ['observations' => [number_format($page->referring_domains).' sampled referring domains link to this page.'], 'hypotheses' => ['Maintaining the page and preserving its URL may protect existing referral value.'], 'source_url' => $page->url, 'data_source' => 'dataforseo_estimate', 'collected_at' => $audit->started_at?->toIso8601String(), 'audit_id' => $audit->id]]);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function normalize(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $arrayLimits = ['page_ids' => 5, 'observations' => 5, 'hypotheses' => 3, 'improvements' => 5, 'outline' => 8, 'internal_links' => 5];
        $stringLimits = ['title' => 200, 'user_need' => 500, 'relevance_reason' => 500, 'content_format' => 100];
        $arrayStringLimits = ['observations' => 500, 'hypotheses' => 500, 'improvements' => 500, 'outline' => 250, 'internal_links' => 500];

        return collect(array_slice(array_values($items), 0, 3))->filter(fn (mixed $item): bool => is_array($item))->map(function (array $item) use ($arrayLimits, $stringLimits, $arrayStringLimits): array {
            foreach ($arrayLimits as $field => $limit) {
                $item[$field] = array_slice(array_values(array_unique(is_array($item[$field] ?? null) ? $item[$field] : [], SORT_REGULAR)), 0, $limit);
            }

            foreach ($stringLimits as $field => $limit) {
                if (is_string($item[$field] ?? null)) {
                    $item[$field] = Str::limit(trim($item[$field]), $limit, '');
                }
            }

            foreach ($arrayStringLimits as $field => $limit) {
                $item[$field] = array_map(
                    fn (mixed $value): mixed => is_string($value) ? Str::limit(trim($value), $limit, '') : $value,
                    $item[$field],
                );
            }

            return $item;
        })->all();
    }
}

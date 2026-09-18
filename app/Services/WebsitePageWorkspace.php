<?php

namespace App\Services;

use App\Models\SearchOpportunity;
use App\Models\SeoImpact;
use App\Models\SeoTargetKeywordRanking;
use App\Models\Website;
use Illuminate\Support\Collection;

class WebsitePageWorkspace
{
    public function __construct(private SeoImpactTracker $tracker) {}

    public function forWebsite(Website $website, Collection $actions, ?string $selectedUrl): array
    {
        $report = $website->healthReports()->where('status', 'completed')->with('pages')->latest('id')->first();
        $audits = $report?->pages ?? collect();
        $impacts = SeoImpact::where('website_id', $website->id)->latest('id')->get();
        $rankings = SeoTargetKeywordRanking::where('website_id', $website->id)->whereNotNull('ranking_url')->with('targetKeyword')->latest('observed_at')->limit(1000)->get();
        $urls = $audits->pluck('url')->concat($actions->pluck('url'))->concat($impacts->pluck('target_urls')->flatten())->concat($rankings->pluck('ranking_url'))->filter()->unique();
        $pages = $urls->filter(fn ($url) => $this->tracker->websiteUrls($website, [$url]) !== [])
            ->unique(fn ($url) => $this->tracker->urlKey($url))->map(function ($url) use ($audits, $actions, $impacts, $report) {
                $key = $this->tracker->urlKey($url);
                $audit = $audits->first(fn ($page) => $this->tracker->urlKey($page->url) === $key);

                return ['url' => $url, 'key' => $key, 'audit' => $audit, 'audited_at' => $report?->completed_at,
                    'actions' => $actions->filter(fn ($action) => $action['url'] && $this->tracker->urlKey($action['url']) === $key)->values(),
                    'impacts' => $impacts->filter(fn ($impact) => collect($impact->target_urls)->contains(fn ($target) => $this->tracker->urlKey($target) === $key))->values()];
            })->sortBy('url')->values();
        $selected = $selectedUrl ? $pages->firstWhere('key', $this->tracker->urlKey($selectedUrl)) : null;
        if ($selectedUrl) {
            abort_unless($selected, 404);
            $selected['rankings'] = $rankings->filter(fn ($rank) => $this->tracker->urlKey($rank->ranking_url) === $selected['key'])->unique(fn ($rank) => $rank->seo_target_keyword_id.':'.$rank->location_code.':'.$rank->language_code.':'.$rank->device)->values();
            $selected['search_signals'] = SearchOpportunity::where('website_id', $website->id)->whereNotNull('page')->latest('last_detected_at')->limit(500)->get()
                ->filter(fn ($signal) => $this->tracker->urlKey($signal->page) === $selected['key'])->values();
        }

        return ['pages' => $pages, 'selected' => $selected];
    }
}

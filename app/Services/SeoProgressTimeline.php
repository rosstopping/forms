<?php

namespace App\Services;

use App\Models\SeoImpact;
use App\Models\SeoWin;
use App\Models\Website;
use Illuminate\Support\Collection;

class SeoProgressTimeline
{
    /** @return Collection<int, array<string, mixed>> */
    public function forWebsite(Website $website): Collection
    {
        $wins = SeoWin::where('website_id', $website->id)->where('category', 'win')->whereNull('dismissed_at')
            ->whereNotNull('approved_at')->whereNotNull('shared_at')->latest('confirmed_at')->limit(20)->get()
            ->map(fn (SeoWin $win): array => ['key' => 'win:'.$win->id, 'type' => 'SEO milestone', 'title' => $win->title,
                'text' => $win->shared_text, 'at' => $win->confirmed_at, 'urls' => [], 'keywords' => []]);
        $publications = SeoImpact::where('website_id', $website->id)->whereNotNull('live_at')->whereNotNull('verified_at')
            ->where('verified_at', '<=', now())->whereIn('verification_status', ['passed', 'checked'])->where('live_at', '<=', now())->latest('live_at')->limit(20)->get()
            ->map(fn (SeoImpact $impact): array => ['key' => 'publication:'.$impact->id, 'type' => 'Published or updated content',
                'title' => $impact->title, 'text' => 'Recorded publication or change date. Page availability has been checked; performance is measured separately.',
                'at' => $impact->live_at, 'urls' => $impact->target_urls, 'keywords' => $impact->target_queries]);
        $work = app(DashboardWorkActivity::class)->forWebsites([$website->id])['completed']
            ->map(fn (array $item): array => ['key' => 'work:'.$item['type'].':'.$item['at']->toIso8601String(),
                'type' => 'Completed Sitewell work', 'title' => $item['type'].' · '.$item['status'],
                'text' => $item['status'] === 'Merged' ? 'The work has been merged. Publication is recorded separately when the page is verified.' : 'Recorded as completed.',
                'at' => $item['at'], 'urls' => [], 'keywords' => []]);

        return $wins->concat($publications)->concat($work)->sortByDesc('at')->take(30)->values();
    }
}

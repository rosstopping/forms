<?php

namespace App\Services;

use App\Models\WebsiteGrowthPlan;
use Illuminate\Support\Str;

class WebsiteGrowthPlanPresentation
{
    /** @return array<string, mixed> */
    public function for(WebsiteGrowthPlan $plan): array
    {
        $report = $plan->report;
        $allKeywords = collect($report['keywords'] ?? []);
        $focusIds = $report['focus_keyword_ids'] ?? [];
        $focusKeywords = $focusIds !== [] ? $allKeywords->filter(fn (array $keyword): bool => in_array($keyword['keyword_id'] ?? null, $focusIds, true)) : $allKeywords;
        $keywords = $focusKeywords->sortBy(fn (array $keyword): int => $focusIds !== []
            ? (($index = array_search($keyword['keyword_id'] ?? null, $focusIds, true)) !== false ? $index : 100)
            : (($keyword['assignment_role'] ?? '') === 'primary' ? 0 : 1))->take(6)->values();
        $priorities = collect($report['priorities'] ?? []);
        if ($priorities->isEmpty()) {
            $priorities = $keywords->filter(fn (array $keyword): bool => filled($keyword['action'] ?? null))
                ->unique(fn (array $keyword): string => $keyword['intended_url'] ?: $keyword['term'])
                ->take(3)->map(fn (array $keyword): array => ['title' => $keyword['term'], 'action' => $keyword['action'], 'outcome' => '']);
        }
        if ($priorities->isEmpty()) {
            $priorities = collect($report['roadmap'] ?? [])->take(3)->values()->map(fn (string $action, int $index): array => [
                'title' => ['Build the foundations', 'Strengthen key pages', 'Grow relevant enquiries'][$index], 'action' => $action, 'outcome' => '',
            ]);
        }
        $observed = $keywords->filter(fn (array $keyword): bool => ($keyword['baseline'] ?? null) !== null);
        $ranked = $keywords->filter(fn (array $keyword): bool => ($keyword['baseline']['status'] ?? null) === 'ranked');
        $health = $report['health'] ?? null;
        $dates = $keywords->pluck('baseline.observed_at')->filter()->sort()->values();

        $intro = $this->heroCopy($report['summary'] ?? '', 36, 'A focused six-month plan to improve your priority pages and reach more of the right people.');
        $goal = $this->heroCopy($report['success_measure'] ?? 'Relevant searches. Better pages. More qualified enquiries.', 20, 'Measure progress through relevant search visibility, useful website visits and meaningful customer actions.');

        return [
            'intro_full' => $intro['full'], 'intro_more' => $intro['more'],
            'goal_full' => $goal['full'], 'goal_more' => $goal['more'],
            'headline' => $this->short($report['headline'] ?? 'A clearer route to growth.', 10),
            'intro' => $intro['excerpt'],
            'goal' => $goal['excerpt'],
            'priorities' => $priorities->take(3)->map(fn (array $priority): array => [
                'title' => $this->short($priority['title'], 7), 'action' => $this->short($priority['action'], 24),
                'outcome' => $this->short($priority['outcome'] ?? '', 12),
            ])->values()->all(),
            'keywords' => $keywords->all(),
            'focus_count' => $keywords->count(),
            'top_ten_count' => $observed->isNotEmpty() ? $ranked->filter(fn (array $keyword): bool => $keyword['baseline']['position'] <= 10)->count() : null,
            'flagged_checks' => $health ? $health['failed'] + $health['warnings'] : null,
            'health_date' => $health['completed_at'] ?? null,
            'rank_dates' => $dates->isEmpty() ? [] : [$dates->first(), $dates->last()],
            'roadmap' => collect($report['roadmap'] ?? [])->take(6)->map(fn (string $action): string => $this->short($action, 22))->values()->all(),
            'dependencies' => $this->sentence($report['dependencies'] ?? '', 24),
            'limitations' => $this->sentence($report['limitations'] ?? '', 32),
        ];
    }

    /** @return array{full: string, excerpt: string, more: bool} */
    private function heroCopy(string $text, int $words, string $fallback): array
    {
        $full = Str::squish($text);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $full);
        $first = $sentences[0] ?? '';
        $excerpt = Str::wordCount($first) <= $words ? $first : $fallback;

        return ['full' => $full, 'excerpt' => $excerpt, 'more' => $full !== $excerpt];
    }

    private function sentence(string $text, int $words): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', Str::squish($text));

        return $this->short($sentences[0] ?? '', $words);
    }

    private function short(string $text, int $words): string
    {
        return Str::words(Str::squish($text), $words, '…');
    }
}

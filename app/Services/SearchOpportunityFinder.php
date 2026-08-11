<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchOpportunityFinder
{
    public function __construct(
        protected ?int $configuredMinimumImpressions = null,
        protected ?int $configuredMinimumEmergingImpressions = null,
        protected ?float $configuredMaximumCtr = null,
        protected ?float $configuredDeclineRatio = null,
        protected ?int $configuredPerTypeLimit = null,
    ) {}

    /**
     * @param  array<int, array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}>  $currentRows
     * @param  array<int, array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}>  $previousRows
     * @return array<int, array<string, mixed>>
     */
    public function find(array $currentRows, array $previousRows): array
    {
        $previous = collect($previousRows)->keyBy(fn (array $row): string => $this->rowKey($row));
        $opportunities = collect();

        foreach ($currentRows as $row) {
            $prior = $previous->get($this->rowKey($row));

            if ($row['impressions'] >= $this->minimumImpressions() && $row['position'] >= 4 && $row['position'] <= 20) {
                $opportunities->push($this->opportunity('ranking_gap', $row, [
                    'title' => 'Ranking opportunity for “'.$row['query'].'”',
                    'summary' => sprintf('This page averaged position %.1f from %s impressions.', $row['position'], number_format($row['impressions'])),
                    'recommendation' => 'Improve the page so it answers this search intent more completely, strengthens relevant internal links, and presents a clearer search result.',
                    'priority_score' => $row['impressions'] * (21 - $row['position']),
                ], $prior));
            }

            if ($row['impressions'] >= $this->minimumImpressions() && $row['position'] <= 10 && $row['ctr'] < $this->maximumCtr()) {
                $opportunities->push($this->opportunity('low_ctr', $row, [
                    'title' => 'Low click-through rate for “'.$row['query'].'”',
                    'summary' => sprintf('The result appeared %s times with a %.1f%% click-through rate at position %.1f.', number_format($row['impressions']), $row['ctr'] * 100, $row['position']),
                    'recommendation' => 'Review the title and description against the query intent, keeping every claim accurate and consistent with the page.',
                    'priority_score' => $row['impressions'] * max($this->maximumCtr() - $row['ctr'], 0) * 100,
                ], $prior));
            }

            if ($prior && $prior['clicks'] >= 5 && $row['clicks'] <= $prior['clicks'] * (1 - $this->declineRatio())) {
                $lostClicks = $prior['clicks'] - $row['clicks'];
                $opportunities->push($this->opportunity('declining', $row, [
                    'title' => 'Declining clicks for “'.$row['query'].'”',
                    'summary' => sprintf('Clicks fell from %s to %s between comparable 28-day periods.', number_format($prior['clicks']), number_format($row['clicks'])),
                    'recommendation' => 'Review whether the page is still current, satisfies the query, and retains the useful coverage and internal links it previously had.',
                    'priority_score' => $lostClicks * 100 + $row['impressions'],
                ], $prior));
            }

            if (! $prior && $row['impressions'] >= $this->minimumEmergingImpressions()) {
                $opportunities->push($this->opportunity('emerging', $row, [
                    'title' => 'Emerging query: “'.$row['query'].'”',
                    'summary' => sprintf('This query generated %s new impressions in the latest period.', number_format($row['impressions'])),
                    'recommendation' => 'Confirm that the existing page is the right destination and strengthen it only where the new query represents a genuine customer need.',
                    'priority_score' => $row['impressions'] * 2,
                ], null));
            }
        }

        $this->cannibalisationOpportunities(collect($currentRows))->each(fn (array $opportunity) => $opportunities->push($opportunity));

        return $opportunities
            ->groupBy('type')
            ->flatMap(fn (Collection $items): Collection => $items->sortByDesc('priority_score')->take($this->perTypeLimit()))
            ->sortByDesc('priority_score')
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function cannibalisationOpportunities(Collection $rows): Collection
    {
        return $rows->groupBy(fn (array $row): string => Str::lower(trim($row['query'])))
            ->map(function (Collection $queryRows): ?array {
                $pages = $queryRows->pluck('page')->filter()->unique()->values();
                $impressions = (float) $queryRows->sum('impressions');
                if ($pages->count() < 2 || $impressions < $this->minimumImpressions()) {
                    return null;
                }

                $query = (string) $queryRows->first()['query'];

                return [
                    'fingerprint' => $this->fingerprint('cannibalisation', $query, null),
                    'type' => 'cannibalisation',
                    'query' => $query,
                    'page' => null,
                    'title' => 'Multiple pages compete for “'.$query.'”',
                    'summary' => $pages->count().' pages appeared for this query across '.number_format($impressions).' impressions.',
                    'recommendation' => 'Choose the best primary page, consolidate overlapping coverage where appropriate, and improve internal linking without deleting useful distinct content.',
                    'metrics' => ['impressions' => $impressions, 'pages' => $pages->take(10)->all()],
                    'priority_score' => $impressions * $pages->count(),
                ];
            })->filter()->values();
    }

    /** @param array<string, mixed> $copy @return array<string, mixed> */
    protected function opportunity(string $type, array $row, array $copy, ?array $previous): array
    {
        return [
            'fingerprint' => $this->fingerprint($type, $row['query'], $row['page']),
            'type' => $type,
            'query' => $row['query'],
            'page' => $row['page'],
            ...$copy,
            'metrics' => ['current' => $row, 'previous' => $previous],
        ];
    }

    protected function fingerprint(string $type, string $query, ?string $page): string
    {
        return hash('sha256', implode('|', [$type, Str::lower(trim($query)), Str::lower(trim((string) $page))]));
    }

    protected function rowKey(array $row): string
    {
        return Str::lower(trim($row['query'])).'|'.Str::lower(trim($row['page']));
    }

    protected function minimumImpressions(): int
    {
        return $this->configuredMinimumImpressions ?? (int) config('forms.search_opportunities.minimum_impressions');
    }

    protected function minimumEmergingImpressions(): int
    {
        return $this->configuredMinimumEmergingImpressions ?? (int) config('forms.search_opportunities.minimum_emerging_impressions');
    }

    protected function maximumCtr(): float
    {
        return $this->configuredMaximumCtr ?? (float) config('forms.search_opportunities.maximum_ctr');
    }

    protected function declineRatio(): float
    {
        return $this->configuredDeclineRatio ?? (float) config('forms.search_opportunities.decline_ratio');
    }

    protected function perTypeLimit(): int
    {
        return $this->configuredPerTypeLimit ?? (int) config('forms.search_opportunities.per_type_limit');
    }
}

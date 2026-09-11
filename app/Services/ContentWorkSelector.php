<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\ContentRequest;
use App\Models\SeoTargetKeyword;
use Illuminate\Support\Collection;

class ContentWorkSelector
{
    public function __construct(private SeoTargetKeywordSelector $targets) {}

    /** @return array{requests: Collection, target: ?SeoTargetKeyword, snapshot: array} */
    public function select(ContentGeneration $generation, bool $applyCooldown = true): array
    {
        $website = $generation->plan->website;
        $active = $this->targets->active($website);
        $snapshot = $this->targets->snapshot($active);
        $history = $generation->plan->generations()->whereKeyNot($generation->id)
            ->where(function ($query): void {
                $query->where('status', ContentGeneration::STATUS_PULL_REQUEST_OPEN)
                    ->orWhere('started_at', '>', now()->subDays(14));
            })->with('contentRequests')->get();
        $openKeys = $history->filter(fn (ContentGeneration $previous): bool => $previous->status === ContentGeneration::STATUS_PULL_REQUEST_OPEN
            && $previous->pull_request_state !== 'closed')->flatMap(fn (ContentGeneration $previous): array => $this->generationKeys($previous))->unique();
        $recentKeys = $history->filter(fn (ContentGeneration $previous): bool => $previous->copilot_task_id && $previous->started_at?->greaterThan(now()->subDays(14)) === true)
            ->flatMap(fn (ContentGeneration $previous): array => $this->generationKeys($previous))->unique();
        $requests = $website->contentRequests()->pendingInQueueOrder()->get()
            ->reject(fn (ContentRequest $request): bool => $openKeys->intersect($this->requestKeys($request))->isNotEmpty())->take(2);
        $eligible = $active->filter(function (SeoTargetKeyword $keyword) use ($snapshot, $openKeys, $recentKeys, $applyCooldown): bool {
            $row = collect($snapshot)->firstWhere('id', $keyword->id);
            $keys = $this->targetKeys($keyword->id, $keyword->term, $row['ranking_url'] ?? null);

            return $openKeys->intersect($keys)->isEmpty()
                && (! $applyCooldown || (($keyword->last_selected_at === null || $keyword->last_selected_at->lessThanOrEqualTo(now()->subDays(14))) && $recentKeys->intersect($keys)->isEmpty()));
        });

        return ['requests' => $requests, 'target' => $requests->isEmpty() ? $this->targets->select($eligible) : null, 'snapshot' => $snapshot];
    }

    /** @return list<string> */
    private function generationKeys(ContentGeneration $generation): array
    {
        $target = collect($generation->target_keyword_context ?? [])->firstWhere('id', $generation->seo_target_keyword_id);
        $keys = $generation->seo_target_keyword_id
            ? $this->targetKeys($generation->seo_target_keyword_id, $target['term'] ?? '', $target['ranking_url'] ?? null) : [];

        return [...$keys, ...$generation->contentRequests->flatMap(fn (ContentRequest $request): array => $this->requestKeys($request))->all()];
    }

    /** @return list<string> */
    private function targetKeys(int $id, string $term, ?string $url): array
    {
        return array_values(array_filter(['target:'.$id, $term !== '' ? 'term:'.mb_strtolower(trim($term)) : null, $url ? $this->urlKey($url) : null]));
    }

    /** @return list<string> */
    private function requestKeys(ContentRequest $request): array
    {
        preg_match_all('~https?://[^\s<>"\)]+~i', $request->instructions, $matches);
        $keys = array_map(fn (string $url): string => $this->urlKey(rtrim($url, '.,;')), $matches[0]);
        $term = data_get($request->competitor_context, 'primary_keyword');
        if (is_string($term) && $term !== '') {
            $keys[] = 'term:'.mb_strtolower(trim($term));
        }

        return $keys;
    }

    private function urlKey(string $url): string
    {
        return 'url:'.mb_strtolower((string) parse_url($url, PHP_URL_HOST)).rtrim((string) parse_url($url, PHP_URL_PATH), '/');
    }
}

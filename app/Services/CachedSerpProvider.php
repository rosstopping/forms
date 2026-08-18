<?php

namespace App\Services;

use App\Contracts\SerpProvider;
use App\Data\SerpResult;
use App\Data\SerpSearchResponse;
use App\Services\DataForSEO\DataForSEOSerpProvider;
use Illuminate\Support\Facades\Cache;

class CachedSerpProvider implements SerpProvider
{
    public function __construct(private DataForSEOSerpProvider $provider) {}

    public function search(string $keyword, string $location, int $depth = 100): SerpSearchResponse
    {
        $generated = false;
        $payload = Cache::remember($this->key($keyword, $location, $depth), now()->addDays($this->cacheDays()), function () use ($keyword, $location, $depth, &$generated): array {
            $generated = true;

            return $this->toPayload($this->provider->search($keyword, $location, $depth));
        });

        return $this->fromPayload($payload, ! $generated);
    }

    public function forget(string $keyword, string $location, int $depth): void
    {
        Cache::forget($this->key($keyword, $location, $depth));
    }

    private function key(string $keyword, string $location, int $depth): string
    {
        $parameters = [
            'keyword' => str($keyword)->squish()->lower()->toString(),
            'location' => str($location)->squish()->lower()->toString(),
            'depth' => min(max($depth, 10), 100),
            'language' => config('services.dataforseo.language_code'),
            'device' => 'desktop',
        ];

        return 'seo-prospect-serp:'.hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR));
    }

    private function cacheDays(): int
    {
        return max(1, (int) config('services.dataforseo.serp_cache_days'));
    }

    /** @return array<string, mixed> */
    private function toPayload(SerpSearchResponse $response): array
    {
        return [
            'provider' => $response->provider,
            'endpoint' => $response->endpoint,
            'results' => $response->results->map(fn (SerpResult $result): array => [
                'position' => $result->position,
                'url' => $result->url,
                'domain' => $result->domain,
                'title' => $result->title,
                'description' => $result->description,
                'website_name' => $result->websiteName,
            ])->all(),
            'cost' => $response->cost,
            'task_id' => $response->taskId,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function fromPayload(array $payload, bool $cached): SerpSearchResponse
    {
        return new SerpSearchResponse(
            provider: (string) $payload['provider'],
            endpoint: (string) $payload['endpoint'],
            results: collect($payload['results'])->map(fn (array $result): SerpResult => new SerpResult(
                position: (int) $result['position'],
                url: (string) $result['url'],
                domain: (string) $result['domain'],
                title: $result['title'],
                description: $result['description'],
                websiteName: $result['website_name'],
            )),
            cost: $cached ? 0 : (float) $payload['cost'],
            taskId: $cached ? null : $payload['task_id'],
            cached: $cached,
            fetchedAt: (string) $payload['fetched_at'],
        );
    }
}

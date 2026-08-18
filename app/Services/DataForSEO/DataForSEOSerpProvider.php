<?php

namespace App\Services\DataForSEO;

use App\Contracts\SerpProvider;
use App\Data\SerpResult;
use App\Data\SerpSearchResponse;

class DataForSEOSerpProvider implements SerpProvider
{
    public const ENDPOINT = 'serp/google/organic/live/advanced';

    public function __construct(private DataForSEOClient $client) {}

    public function search(string $keyword, string $location, int $depth = 100): SerpSearchResponse
    {
        $response = $this->client->post(self::ENDPOINT, [
            'keyword' => $keyword,
            'location_name' => $location,
            'language_code' => (string) config('services.dataforseo.language_code'),
            'device' => 'desktop',
            'depth' => min(max($depth, 10), 100),
        ]);

        $results = collect(data_get($response->results, '0.items', []))
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'organic')
            ->map(fn (array $item): SerpResult => new SerpResult(
                position: (int) ($item['rank_group'] ?? $item['rank_absolute'] ?? 0),
                url: (string) ($item['url'] ?? ''),
                domain: (string) ($item['domain'] ?? parse_url((string) ($item['url'] ?? ''), PHP_URL_HOST)),
                title: is_string($item['title'] ?? null) ? $item['title'] : null,
                description: is_string($item['description'] ?? null) ? $item['description'] : null,
                websiteName: is_string($item['website_name'] ?? null) ? $item['website_name'] : null,
            ))
            ->filter(fn (SerpResult $result): bool => $result->position > 0 && $result->url !== '' && $result->domain !== '')
            ->values();

        return new SerpSearchResponse('dataforseo', self::ENDPOINT, $results, $response->cost, $response->taskId);
    }
}

<?php

namespace App\Jobs;

use App\Contracts\SerpProvider;
use App\Data\SerpSearchResponse;
use App\Models\ExternalApiUsage;
use App\Models\Prospect;
use App\Models\SeoProspectSearch;
use App\Services\PixelUrlNormalizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DiscoverSeoProspects implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public SeoProspectSearch $search) {}

    public function uniqueId(): string
    {
        return (string) $this->search->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SerpProvider $provider, PixelUrlNormalizer $urls): void
    {
        $this->search->update(['status' => 'running', 'error' => null, 'started_at' => now(), 'completed_at' => null]);
        $errors = [];
        $successfulKeywords = 0;

        foreach ($this->search->keywords as $keyword) {
            try {
                $response = $provider->search($keyword, $this->search->location, $this->search->maximum_position);
                $successfulKeywords++;
                $this->recordUsage($keyword, $response);

                foreach ($response->results as $result) {
                    if ($result->position > $this->search->maximum_position) {
                        continue;
                    }

                    try {
                        $domain = $urls->normalizeHost($result->domain);
                    } catch (InvalidArgumentException) {
                        continue;
                    }

                    $candidate = $this->search->candidates()->updateOrCreate(
                        ['domain' => $domain],
                        ['prospect_id' => $this->existingProspect($domain)?->id, 'website_url' => $this->origin($result->url, $domain), 'business_name' => $result->websiteName, 'location' => $this->search->location],
                    );
                    $candidate->rankings()->updateOrCreate(
                        ['keyword' => $keyword],
                        ['position' => $result->position, 'ranking_url' => $result->url, 'page_title' => $result->title, 'description' => $result->description, 'checked_at' => now()],
                    );
                }
            } catch (Throwable $exception) {
                $errors[] = $keyword.': '.$exception->getMessage();
            }
        }

        if ($successfulKeywords === 0) {
            throw new RuntimeException($errors[0] ?? 'No SERP searches completed.');
        }

        $candidates = $this->search->candidates()->whereIn('qualification_status', ['pending_analysis', 'analysis_failed'])->get();
        $terminalStatus = $errors === [] ? 'analyzed' : 'analyzed_with_errors';
        $this->search->update(['status' => $candidates->isEmpty() ? $terminalStatus : 'analyzing', 'candidate_count' => $this->search->candidates()->count(), 'error' => $errors === [] ? null : implode("\n", $errors), 'completed_at' => $candidates->isEmpty() ? now() : null]);

        foreach ($candidates as $candidate) {
            AnalyzeSeoProspectCandidate::dispatch($candidate);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->search->update(['status' => 'failed', 'error' => $exception?->getMessage(), 'completed_at' => now()]);
    }

    private function recordUsage(string $keyword, SerpSearchResponse $response): void
    {
        ExternalApiUsage::query()->create([
            'provider' => $response->provider,
            'endpoint' => $response->endpoint,
            'request_type' => 'live',
            'result_count' => $response->results->count(),
            'cost' => $response->cost,
            'provider_task_id' => $response->taskId,
            'metadata' => ['seo_prospect_search_id' => $this->search->id, 'keyword' => $keyword, 'location' => $this->search->location],
            'requested_at' => now(),
        ]);
        $this->search->increment('api_cost', $response->cost);
    }

    private function existingProspect(string $domain): ?Prospect
    {
        return Prospect::query()->where('website_url', 'like', '%'.$domain.'%')->get()
            ->first(fn (Prospect $prospect): bool => preg_replace('/^www\./i', '', strtolower((string) parse_url($prospect->website_url, PHP_URL_HOST))) === $domain);
    }

    private function origin(string $url, string $domain): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return (in_array($scheme, ['http', 'https'], true) ? $scheme : 'https').'://'.($host ?: $domain);
    }
}

<?php

namespace App\Jobs;

use App\Models\AiVisibilityResult;
use App\Models\AiVisibilitySetting;
use App\Models\ExternalApiUsage;
use App\Services\AiVisibilityAnalyzer;
use App\Services\AiVisibilityProviderRegistry;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class CheckAiVisibility implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public int $uniqueFor = 86400;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public AiVisibilityResult $result) {}

    public function retryUntil(): DateTimeInterface
    {
        return $this->result->created_at->copy()->addDays(6);
    }

    public function uniqueId(): string
    {
        return (string) $this->result->id;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new RateLimited('ai-visibility'))->releaseAfter(60)];
    }

    public function handle(AiVisibilityProviderRegistry $providers, AiVisibilityAnalyzer $analyzer): void
    {
        Cache::lock('ai-visibility-check:'.$this->result->id, 150)->block(1, function () use ($providers, $analyzer): void {
            $result = $this->result->fresh(['website.owner', 'prompt']);
            if (! $result || in_array($result->status, ['completed', 'cancelled'], true)) {
                return;
            }
            $settings = AiVisibilitySetting::query()->where('website_id', $result->website_id)->first();
            $provider = $providers->get($result->provider);
            if (! $settings?->enabled || ! in_array($result->provider, $settings->providers ?? [], true) || ! $provider->available() || ! $result->website->is_active || ! $result->website->owner?->hasActiveMembership() || ! $result->prompt || $result->prompt->trashed() || ! $result->prompt->active || $result->prompt->fingerprint !== $result->prompt_fingerprint) {
                $result->update(['status' => 'cancelled', 'error' => 'Tracking, prompt, provider or website eligibility changed before this check ran.']);

                return;
            }
            if ($result->attempts >= 3) {
                $result->update(['status' => 'failed', 'error' => 'The provider could not complete this check after three attempts.']);

                return;
            }
            $result->update(['status' => 'running', 'started_at' => now(), 'attempts' => $result->attempts + 1, 'error' => null]);
            try {
                $response = $provider->check($result->prompt_snapshot, $result->model);
                if (trim($response['text']) === '' || mb_strlen($response['text']) > config('ai_visibility.max_response_characters')) {
                    throw new RuntimeException('The provider response was empty or exceeded the evidence limit.');
                }
                $analysis = $analyzer->analyze($response['text'], $response['citations'], $result->identity_snapshot);
                $result->update([...$analysis, 'analysis' => [...$analysis['analysis'], 'provider' => $response['metadata']], 'response_text' => $response['text'], 'usage' => $response['usage'], 'cohort' => hash('sha256', json_encode([$result->prompt_fingerprint, $result->identity_snapshot, $response['metadata']['returned_model'] ?? $result->model, $result->provider, $analysis['analysis']['version'], $response['metadata']['instructions'] ?? null], JSON_THROW_ON_ERROR)), 'status' => 'completed', 'checked_at' => now(), 'error' => null]);
            } catch (Throwable $exception) {
                $result->update(['status' => 'failed', 'error' => 'The provider could not complete this check. Sitewell will retry up to three times.']);
                report($exception);
                throw $exception;
            }
            try {
                ExternalApiUsage::query()->create(['website_id' => $result->website_id, 'provider' => $result->provider, 'endpoint' => 'ai_visibility', 'request_type' => 'ai_visibility_check', 'result_count' => 1, 'cost' => data_get($response, 'usage.cost.total_cost'), 'metadata' => ['ai_visibility_result_id' => $result->id, 'model' => $result->model, 'usage' => $response['usage']], 'requested_at' => $result->started_at]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        AiVisibilityResult::query()->whereKey($this->result->id)->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'failed', 'error' => 'This check could not finish. Failed checks are excluded from visibility scores.']);
    }
}

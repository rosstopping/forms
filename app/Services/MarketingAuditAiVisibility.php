<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MarketingAuditAiVisibility
{
    public function __construct(private OpenAiVisibilityProvider $provider, private AiVisibilityAnalyzer $analyzer) {}

    public function available(): bool
    {
        return $this->provider->available();
    }

    public function question(string $domain, ?array $seo): ?string
    {
        return $this->questions($domain, $seo)[0] ?? null;
    }

    /** @return array<int, string> */
    public function questions(string $domain, ?array $seo): array
    {
        $brand = Str::before(preg_replace('/^www\./', '', Str::lower($domain)), '.');

        return collect($seo['keywords'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && is_string($item['term'] ?? null))
            ->map(fn (array $item): string => Str::squish($item['term']))
            ->filter(fn (string $term): bool => count(preg_split('/\s+/u', $term)) >= 2
                && mb_strlen($term) <= 75
                && ! str_contains(Str::lower($term), $brand)
                && ! preg_match('/^(what|why|how|when|where|who)\b/iu', $term))
            ->unique(fn (string $term): string => Str::lower($term))
            ->take(2)
            ->map(fn (string $term): string => 'Which businesses would you recommend for '.$term.'?')
            ->values()->all();
    }

    /** @return array<string, mixed> */
    public function check(string $domain, string $question): array
    {
        $model = $this->provider->model();
        $key = 'marketing-audit-ai:'.hash('sha256', Str::lower($domain).'|'.$model.'|'.$question);

        return Cache::remember($key, now()->addDays(7), function () use ($domain, $question, $model): array {
            $response = $this->provider->check($question, $model);
            if (trim($response['text']) === '') {
                throw new \RuntimeException('The AI provider returned an empty response.');
            }

            $analysis = $this->analyzer->analyze($response['text'], $response['citations'], ['names' => [], 'domains' => [$domain]]);

            return [
                'status' => 'completed',
                'question' => $question,
                'provider' => 'OpenAI',
                'checked_at' => now()->toIso8601String(),
                'website_mentioned' => $analysis['website_mentioned'],
                'website_cited' => $analysis['website_cited'],
            ];
        });
    }
}

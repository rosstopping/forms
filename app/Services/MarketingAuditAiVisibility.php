<?php

namespace App\Services;

use App\Ai\Agents\AuditServiceQuestionSelector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class MarketingAuditAiVisibility
{
    public function __construct(private OpenAiVisibilityProvider $provider, private AiVisibilityAnalyzer $analyzer)
    {
        $this->provider = $provider->forPublicAudit();
    }

    public function available(): bool
    {
        return $this->provider->available();
    }

    public function question(string $domain, ?array $seo, string $businessContext = ''): ?string
    {
        return $this->questions($domain, $seo, $businessContext)[0] ?? null;
    }

    /** @return array<int, string> */
    public function questions(string $domain, ?array $seo, string $businessContext = ''): array
    {
        return $this->selection($domain, $businessContext)['questions'];
    }

    /** @return array{questions: array<int, string>, evidence: array<int, array<string, string>>, method: string} */
    public function selection(string $domain, string $businessContext): array
    {
        $empty = ['questions' => [], 'evidence' => [], 'method' => 'own_services_v2'];
        $context = Str::squish(mb_substr($businessContext, 0, 12000));
        if (mb_strlen($context) < 40) {
            return $empty;
        }
        $key = 'marketing-audit-service-questions:v2:'.hash('sha256', Str::lower($domain).'|'.$context);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        try {
            $response = (new AuditServiceQuestionSelector)->prompt(json_encode(['domain' => $domain, 'website_text' => $context], JSON_THROW_ON_ERROR), timeout: 35)->toArray();
            $selection = $this->validatedSelection($domain, $context, $response);
            Cache::put($key, $selection, now()->addDays(7));

            return $selection;
        } catch (Throwable $exception) {
            report($exception);

            return $empty;
        }
    }

    /** @param array<string, mixed> $selection
     * @return array{questions: array<int, string>, evidence: array<int, array<string, string>>, method: string}
     */
    public function validatedSelection(string $domain, string $context, array $selection): array
    {
        $result = ['questions' => [], 'evidence' => [], 'method' => 'own_services_v2'];
        if (($selection['supported'] ?? false) !== true) {
            return $result;
        }
        $context = Str::lower(Str::squish($context));
        $brand = Str::before(preg_replace('/^www\./', '', Str::lower($domain)), '.');
        $clients = collect($selection['client_names'] ?? [])->filter(fn ($name): bool => is_string($name) && mb_strlen(trim($name)) >= 3 && Str::contains($context, Str::lower(Str::squish($name))))->map(fn (string $name): string => Str::lower(Str::squish($name)))->all();
        $seen = [];
        foreach (array_slice($selection['services'] ?? [], 0, 6) as $item) {
            $service = Str::squish((string) ($item['service'] ?? ''));
            $quote = Str::squish((string) ($item['evidence'] ?? ''));
            $normalised = Str::lower($service);
            if (mb_strlen($service) < 3 || mb_strlen($service) > 90 || mb_strlen($quote) < 15 || mb_strlen($quote) > 350
                || ! preg_match("~^[\pL\pN &+'()/–-]+$~u", $service) || count(explode(' ', $service)) > 10
                || ! Str::contains($context, Str::lower($quote)) || ! Str::contains(Str::lower($quote), $normalised)
                || Str::contains($normalised, [$brand, ...$clients]) || isset($seen[$normalised])) {
                continue;
            }
            $question = 'Which businesses would you recommend for '.$service.'?';
            $seen[$normalised] = true;
            $result['questions'][] = $question;
            $result['evidence'][] = ['service' => $service, 'quote' => $quote, 'question' => $question];

        }

        usort($result['evidence'], fn (array $a, array $b): int => $this->isSeoService($b['service']) <=> $this->isSeoService($a['service']));
        $result['evidence'] = array_slice($result['evidence'], 0, 2);
        $result['questions'] = array_column($result['evidence'], 'question');

        return $result;
    }

    private function isSeoService(string $service): bool
    {
        return preg_match('/\bseo\b|search engine optimi[sz]ation/iu', $service) === 1;
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

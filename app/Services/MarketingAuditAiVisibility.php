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
        $empty = ['questions' => [], 'evidence' => [], 'method' => 'natural_buyer_questions_v2'];
        $context = Str::squish(mb_substr($businessContext, 0, 12000));
        if (mb_strlen($context) < 40) {
            return $empty;
        }
        $key = 'marketing-audit-service-questions:v4:'.hash('sha256', Str::lower($domain).'|'.$context);
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        try {
            $response = (new AuditServiceQuestionSelector)->prompt(json_encode(['domain' => $domain, 'website_text' => $context], JSON_THROW_ON_ERROR), provider: 'openai', timeout: 35)->toArray();
            $selection = $this->validatedSelection($domain, $context, $response);
            if ($selection['questions'] !== []) {
                Cache::put($key, $selection, now()->addDays(7));
            }

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
        $result = ['questions' => [], 'evidence' => [], 'method' => 'natural_buyer_questions_v2'];
        if (($selection['supported'] ?? false) !== true) {
            return $result;
        }
        $context = Str::lower(Str::squish($context));
        $brand = Str::before(preg_replace('/^www\./', '', Str::lower($domain)), '.');
        $clients = collect($selection['client_names'] ?? [])->filter(fn ($name): bool => is_string($name) && mb_strlen(trim($name)) >= 3 && Str::contains($context, Str::lower(Str::squish($name))))->map(fn (string $name): string => Str::lower(Str::squish($name)))->all();
        $businessName = Str::lower(Str::squish((string) ($selection['business_name'] ?? '')));
        $excluded = [$brand, ...$clients];
        if (mb_strlen($businessName) >= 3 && Str::contains($context, $businessName)) {
            $excluded[] = $businessName;
        }
        $seen = [];
        foreach (array_slice($selection['services'] ?? [], 0, 4) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $service = Str::squish((string) ($item['service'] ?? ''));
            $quote = Str::squish((string) ($item['evidence'] ?? ''));
            $question = Str::squish((string) ($item['question'] ?? ''));
            $normalised = Str::lower($question);
            $intent = $item['intent'] ?? '';
            $location = Str::squish((string) ($item['location'] ?? ''));
            $locationQuote = Str::squish((string) ($item['location_evidence'] ?? ''));
            if (mb_strlen($service) < 3 || mb_strlen($service) > 90 || mb_strlen($quote) < 8 || mb_strlen($quote) > 350
                || ! Str::contains($context, Str::lower($quote)) || ! Str::contains(Str::lower($quote), Str::lower($service))
                || mb_strlen($question) < 20 || mb_strlen($question) > 200 || ! str_ends_with($question, '?')
                || ! in_array($intent, ['provider', 'booking', 'price', 'comparison'], true)
                || preg_match('~[<>\r\n]|https?://|www\.|[\pL\pN-]+\.(?:com|co\.uk|net|org)\b~iu', $question)
                || preg_match('/^(?:which|what) (?:businesses|companies|providers) (?:would you recommend|are best|do you recommend) (?:for|to)/iu', $question)
                || preg_match('/\byour(?:s)?\b|\byou (?:offer|provide|sell|charge|have|include)\b/iu', $question)
                || preg_match('/^(?:can|could|would|will) you (?:build|create|design|book|sell|provide)\b/iu', $question)
                || preg_match('/[£$€]\s*\d|\b\d+\s*(?:pounds|euros|dollars)\b/iu', $question)
                || Str::contains($normalised, $excluded) || isset($seen[$normalised])) {
                continue;
            }
            if ($location !== '' && (mb_strlen($locationQuote) < 15 || ! Str::contains($context, Str::lower($locationQuote))
                || ! Str::contains(Str::lower($locationQuote), Str::lower($location)) || ! Str::contains($normalised, Str::lower($location)))) {
                continue;
            }
            $tokens = preg_split('/[^\pL\pN]+/u', Str::lower($service), -1, PREG_SPLIT_NO_EMPTY);
            $tokens = array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, ['and', 'for', 'the', 'our', 'services', 'service', 'work', 'custom', 'bespoke'], true));
            if ($tokens === [] || ! collect($tokens)->contains(fn (string $token): bool => Str::contains($normalised, rtrim($token, 's')))) {
                continue;
            }
            $claims = ['commission-free', 'fee-free', 'guaranteed', 'unlimited'];
            if (collect($claims)->contains(fn (string $claim): bool => Str::contains($normalised, $claim) && ! Str::contains($context, $claim))) {
                continue;
            }
            preg_match_all('/\b\d+(?:[.,]\d+)*\b/u', $question, $numbers);
            if (collect($numbers[0])->contains(fn (string $number): bool => ! Str::contains($quote.' '.$locationQuote, $number))) {
                continue;
            }
            preg_match_all('/\b[\p{Lu}][\pL\pN]+\b/u', $question, $names);
            $ordinary = ['Where', 'Who', 'How', 'What', 'Which', 'Can', 'Could', 'Does', 'Do', 'Is', 'Are', 'Should'];
            if (collect($names[0])->contains(fn (string $name): bool => ! in_array($name, $ordinary, true) && ! Str::contains($context, Str::lower($name)))) {
                continue;
            }
            $seen[$normalised] = true;
            $result['questions'][] = $question;
            $result['evidence'][] = ['service' => $service, 'quote' => $quote, 'question' => $question, 'intent' => $intent, 'location' => $location, 'location_quote' => $locationQuote];
            if (count($result['questions']) === 2) {
                break;
            }
        }

        return $result;
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

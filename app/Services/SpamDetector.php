<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SpamDetector
{
    public function isSpam(array $payload): bool
    {
        $fields = collect(Arr::flatten($payload))
            ->filter(fn (mixed $value): bool => is_scalar($value))
            ->map(fn (mixed $value): string => (string) $value);

        $content = $fields->implode("\n");
        $normalizedContent = preg_replace('/(?<=[\pL\pN])\s*\.\s*(?=[\pL])/u', '.', $content) ?? $content;
        $score = 0;
        $urlCount = preg_match_all(
            '/(?:https?:\/\/|www\.)[^\s<]+|(?<![@\pL\pN._-])(?:[a-z0-9-]+\.)+[a-z]{2,63}(?:\/[^\s<]*)?/iu',
            $normalizedContent,
        );

        if ($urlCount > 0 && config('forms.spam.links_are_spam', true)) {
            return true;
        }

        if ($this->containsInvalidEmail($payload)) {
            $score += 2;
        }

        if ($this->hasExplicitlyEmptyContent($payload)) {
            $score += config('forms.spam.empty_content_score', 3);
        }

        if (preg_match('/<\s*a\b[^>]*\bhref\s*=/iu', $content) === 1) {
            $score += config('forms.spam.html_link_score', 3);
        }

        if (Str::contains(Str::lower($content), config('forms.spam.automation_phrases', []))) {
            $score += config('forms.spam.automation_phrase_score', 3);
        }

        if (Str::contains(Str::lower($content), config('forms.spam.promotional_phrases', []))) {
            $score += 2;
        }

        if (Str::contains(Str::lower($content), config('forms.spam.unsolicited_phrases', []))) {
            $score += config('forms.spam.unsolicited_phrase_score', 3);
        }

        if ($this->containsKnownValue($fields->all())) {
            $score += config('forms.spam.threshold', 3);
        }

        if ($this->hasSuspiciousIdentity($payload)) {
            $score += config('forms.spam.suspicious_identity_score', 3);
        }

        if (Str::contains(Str::lower($normalizedContent), config('forms.spam.shortened_link_domains', []))) {
            $score += config('forms.spam.shortened_link_score', 3);
        }

        if (Str::length($content) > config('forms.spam.long_content_length', 4000)) {
            $score++;
        }

        return $score >= config('forms.spam.threshold', 3);
    }

    protected function containsKnownValue(array $fields): bool
    {
        $knownValues = config('forms.spam.known_values', []);

        return collect($fields)->contains(
            fn (string $value): bool => in_array(Str::lower(trim($value)), $knownValues, true),
        );
    }

    protected function hasSuspiciousIdentity(array $payload): bool
    {
        $name = trim((string) (Arr::get($payload, 'name') ?? Arr::get($payload, 'full_name', '')));
        $company = Str::lower(trim((string) Arr::get($payload, 'company', '')));
        $phone = preg_replace('/\D+/', '', (string) (Arr::get($payload, 'phone') ?? Arr::get($payload, 'mobile', ''))) ?? '';
        $hasSuspiciousPhone = preg_match(config('forms.spam.suspicious_phone_pattern', '/^$/'), $phone) === 1;

        return $hasSuspiciousPhone
            && (in_array($company, config('forms.spam.suspicious_company_values', []), true)
                || preg_match(config('forms.spam.suspicious_name_pattern', '/a^/'), $name) === 1);
    }

    protected function containsInvalidEmail(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = Str::of((string) $key)->lower()->replace('-', '_')->toString();
            $isEmailField = $normalizedKey === 'email'
                || $normalizedKey === 'email_address'
                || Str::endsWith($normalizedKey, ['_email', '_email_address']);

            if (! $isEmailField || ! is_scalar($value)) {
                continue;
            }

            $email = trim((string) $value);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return true;
            }
        }

        return false;
    }

    protected function hasExplicitlyEmptyContent(array $payload): bool
    {
        $contentFields = collect($payload)
            ->filter(function (mixed $value, string|int $key): bool {
                $normalizedKey = Str::of((string) $key)->lower()->replace('-', '_')->toString();

                return in_array($normalizedKey, config('forms.spam.content_fields', []), true);
            });

        return $contentFields->isNotEmpty()
            && $contentFields->every(fn (mixed $value): bool => ! is_scalar($value) || blank(trim((string) $value)));
    }
}

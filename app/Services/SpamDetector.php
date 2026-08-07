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
        $score = 0;
        $urlCount = preg_match_all('/(?:https?:\/\/|www\.)[^\s<]+/iu', $content);

        if ($urlCount >= config('forms.spam.max_links', 3)) {
            $score += 3;
        } elseif ($urlCount > 0) {
            $score++;
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

        if (Str::length($content) > config('forms.spam.long_content_length', 4000)) {
            $score++;
        }

        return $score >= config('forms.spam.threshold', 3);
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

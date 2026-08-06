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
}

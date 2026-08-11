<?php

namespace App\Services;

use Illuminate\Support\Str;

class GithubWebhookSignature
{
    public function isValid(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.github.webhook_secret');

        if ($secret === '' || ! Str::startsWith((string) $signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, (string) $signature);
    }
}

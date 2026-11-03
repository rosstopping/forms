<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteDomain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TurnstileVerifier
{
    public function verify(Website $website, ?string $token, ?string $ipAddress): TurnstileVerificationResult
    {
        if (! $website->turnstile_enabled || $website->auto_discovered) {
            return new TurnstileVerificationResult(true, 'not_required');
        }

        if (blank($website->turnstile_secret_key)) {
            return new TurnstileVerificationResult(false, 'configuration_error', true);
        }

        if (blank($token)) {
            return new TurnstileVerificationResult(false, 'missing_token');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(config('services.turnstile.connect_timeout', 3))
                ->timeout(config('services.turnstile.timeout', 5))
                ->post(config('services.turnstile.verify_url'), [
                    'secret' => $website->turnstile_secret_key,
                    'response' => $token,
                    'remoteip' => $ipAddress,
                ]);
        } catch (ConnectionException) {
            return new TurnstileVerificationResult(false, 'verifier_unavailable', true);
        }

        if (! $response->successful() || ! is_bool($response->json('success'))) {
            return new TurnstileVerificationResult(false, 'verifier_unavailable', true);
        }

        if ($response->json('success') === false) {
            $errors = $response->json('error-codes', []);

            if (! is_array($errors) || count(array_filter($errors, 'is_string')) !== count($errors)) {
                return new TurnstileVerificationResult(false, 'verifier_unavailable', true);
            }

            if (array_intersect($errors, ['internal-error', 'missing-input-secret', 'invalid-input-secret', 'bad-request']) !== []) {
                return new TurnstileVerificationResult(false, 'verifier_unavailable', true);
            }

            if (in_array('timeout-or-duplicate', $errors, true)) {
                return new TurnstileVerificationResult(false, 'expired_or_duplicate_token');
            }

            return new TurnstileVerificationResult(false, 'invalid_token');
        }

        $returnedHostname = $response->json('hostname');
        if (! is_string($returnedHostname) || $returnedHostname === '') {
            return new TurnstileVerificationResult(false, 'hostname_mismatch');
        }

        $hostname = Str::lower($returnedHostname);
        $hostname = preg_replace('/^www\./i', '', $hostname);
        if ($hostname === '') {
            return new TurnstileVerificationResult(false, 'hostname_mismatch');
        }

        $hostname = idn_to_utf8($hostname, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $hostname;

        $verified = $hostname !== '' && $website->domains()
            ->where('domain', $hostname)
            ->where('ownership_status', WebsiteDomain::OWNERSHIP_VERIFIED)
            ->exists();

        return new TurnstileVerificationResult($verified, $verified ? 'verified' : 'hostname_mismatch');
    }
}

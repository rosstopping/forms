<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WordpressConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WordPressConnectionManager
{
    public function __construct(private PixelUrlNormalizer $urls) {}

    /** @return array{code: string, expires_at: Carbon} */
    public function issuePairingCode(Website $website): array
    {
        do {
            $plainCode = Str::upper(Str::random(12));
            $codeHash = $this->pairingCodeHash($plainCode);
        } while (WordpressConnection::query()->where('pairing_code_hash', $codeHash)->exists());

        $expiresAt = now()->addMinutes(10);

        $website->wordpressConnection()->updateOrCreate([], [
            'pairing_code_hash' => $codeHash,
            'pairing_code_expires_at' => $expiresAt,
        ]);

        return [
            'code' => implode('-', str_split($plainCode, 4)),
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array{connection: WordpressConnection, credential: string, webhook_secret: string} */
    public function connect(string $pairingCode, string $wordpressUrl, string $pluginVersion): array
    {
        return DB::transaction(function () use ($pairingCode, $wordpressUrl, $pluginVersion): array {
            $connection = WordpressConnection::query()
                ->with('website.domains')
                ->where('pairing_code_hash', $this->pairingCodeHash($pairingCode))
                ->lockForUpdate()
                ->first();

            if (! $connection || ! $connection->pairing_code_expires_at?->isFuture()) {
                throw ValidationException::withMessages([
                    'code' => 'That connection code is invalid or has expired.',
                ]);
            }

            $this->ensureUrlMatchesWebsite($connection->website, $wordpressUrl);

            $credential = 'swp_'.Str::random(64);
            $webhookSecret = 'swh_'.Str::random(64);

            $connection->update([
                'pairing_code_hash' => null,
                'pairing_code_expires_at' => null,
                'credential_hash' => hash('sha256', $credential),
                'webhook_secret' => $webhookSecret,
                'wordpress_url' => Str::of($wordpressUrl)->trim()->rtrim('/')->toString(),
                'plugin_version' => $pluginVersion,
                'active_release_public_id' => null,
                'connected_at' => now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]);

            return ['connection' => $connection, 'credential' => $credential, 'webhook_secret' => $webhookSecret];
        });
    }

    public function heartbeat(string $publicId, ?string $credential, string $wordpressUrl, string $pluginVersion): WordpressConnection
    {
        $connection = $this->authenticate($publicId, $credential);

        $connection->loadMissing('website.domains');
        $this->ensureUrlMatchesWebsite($connection->website, $wordpressUrl);
        $connection->update([
            'wordpress_url' => Str::of($wordpressUrl)->trim()->rtrim('/')->toString(),
            'plugin_version' => $pluginVersion,
            'last_seen_at' => now(),
        ]);

        return $connection;
    }

    public function authenticate(string $publicId, ?string $credential): WordpressConnection
    {
        $connection = WordpressConnection::query()
            ->where('public_id', $publicId)
            ->whereNull('revoked_at')
            ->first();

        if (! $connection
            || blank($credential)
            || blank($connection->credential_hash)
            || ! hash_equals($connection->credential_hash, hash('sha256', $credential))) {
            abort(401, 'Invalid WordPress connection credentials.');
        }

        return $connection;
    }

    public function revoke(WordpressConnection $connection): void
    {
        $connection->update([
            'pairing_code_hash' => null,
            'pairing_code_expires_at' => null,
            'credential_hash' => null,
            'webhook_secret' => null,
            'revoked_at' => now(),
        ]);
    }

    private function ensureUrlMatchesWebsite(Website $website, string $wordpressUrl): void
    {
        $wordpressHost = $this->urls->normalizeHost((string) parse_url($wordpressUrl, PHP_URL_HOST));
        $matchesWebsite = $website->domains->contains(
            fn ($domain): bool => $this->urls->normalizeHost($domain->domain) === $wordpressHost,
        );

        if (! $matchesWebsite) {
            throw ValidationException::withMessages([
                'site_url' => 'The WordPress hostname does not match this Sitewell website.',
            ]);
        }
    }

    private function pairingCodeHash(string $code): string
    {
        $normalizedCode = Str::of($code)->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();

        return hash_hmac('sha256', $normalizedCode, (string) config('app.key'));
    }
}

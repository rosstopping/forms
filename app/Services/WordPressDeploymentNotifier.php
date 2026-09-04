<?php

namespace App\Services;

use App\Models\WordpressConnection;
use App\Models\WordpressStaticRelease;
use Illuminate\Support\Facades\Http;
use Throwable;

class WordPressDeploymentNotifier
{
    public function notify(WordpressConnection $connection, WordpressStaticRelease $release): bool
    {
        if (! $connection->isConnected()
            || blank($connection->webhook_secret)
            || ! $this->isPublicHttpsUrl($connection->wordpress_url)) {
            return false;
        }

        try {
            Http::acceptJson()
                ->withToken($connection->webhook_secret)
                ->connectTimeout(5)
                ->timeout(30)
                ->retry([250, 1000], throw: false)
                ->post(rtrim($connection->wordpress_url, '/').'/wp-json/sitewell-static-frontend/v1/deploy', [
                    'release_id' => $release->public_id,
                ])
                ->throw();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function isPublicHttpsUrl(?string $url): bool
    {
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);

        return is_array($addresses)
            && $addresses !== []
            && collect($addresses)->every(
                fn (string $address): bool => filter_var(
                    $address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
                ) !== false,
            );
    }
}

<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;

class SitemapFetcher
{
    public function fetch(PendingRequest $request, string $url): Response
    {
        $origin = new Uri($url);
        $current = $origin;
        $visited = [];

        for ($redirects = 0; ; $redirects++) {
            $visited[(string) $current] = true;
            $response = $request->withoutRedirecting()->get((string) $current);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)
                || $redirects >= 5 || ! $response->header('Location')) {
                return $response;
            }

            try {
                $target = UriResolver::resolve($current, new Uri($response->header('Location')))->withFragment('');
            } catch (InvalidArgumentException) {
                return $response;
            }

            if (! in_array($target->getScheme(), ['http', 'https'], true)
                || $target->getHost() !== $origin->getHost()
                || $target->getPort() !== $origin->getPort()
                || $target->getUserInfo() !== ''
                || isset($visited[(string) $target])) {
                return $response;
            }

            $current = $target;
        }
    }
}

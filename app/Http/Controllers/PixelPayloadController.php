<?php

namespace App\Http\Controllers;

use App\Http\Requests\PixelPayloadRequest;
use App\Services\PixelPayloadBuilder;
use App\Services\PixelRequestObserver;
use Illuminate\Http\JsonResponse;
use Throwable;

class PixelPayloadController extends Controller
{
    public function __invoke(
        PixelPayloadRequest $request,
        string $siteKey,
        PixelPayloadBuilder $payloads,
        PixelRequestObserver $observer,
    ): JsonResponse {
        $url = $request->string('url')->toString();

        try {
            $payload = $payloads->build($siteKey, $url);
        } catch (Throwable $exception) {
            $observer->payloadFailed($siteKey, $url, $exception);

            return response()->json(['message' => 'Pixel payload unavailable.'], 503);
        }
        abort_if($payload === null, 404);

        $etag = '"'.hash('sha256', $siteKey.':'.$payload['version'].':'.$payload['url']).'"';
        $response = response()->json($payload)->setEtag($etag);

        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }

        $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=300, stale-if-error=86400');

        return $response;
    }
}

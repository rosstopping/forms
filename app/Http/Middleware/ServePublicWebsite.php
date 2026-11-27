<?php

namespace App\Http\Middleware;

use App\Support\PublicWebsite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ServePublicWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = PublicWebsite::origin();
        if ($origin === null) {
            abort_if($request->getHost() === config('public_website.production_host'), 503);

            return $next($request);
        }

        $route = $request->route();
        $publicHost = $request->getHost() === parse_url($origin, PHP_URL_HOST);
        if ($publicHost) {
            abort_unless(PublicWebsite::owns($route), 404);
            $path = PublicWebsite::path('/'.$request->path(), $route);
            if ($request->isMethodSafe() && $path !== '/'.$request->path()) {
                return redirect()->away($origin.$path.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
            }
        } elseif (PublicWebsite::owns($route) && $request->isMethodSafe()) {
            abort_unless($request->getSchemeAndHttpHost() === rtrim(config('public_website.legacy_url'), '/'), 404);
            $path = PublicWebsite::path('/'.$request->path(), $route);
            $destination = $origin.$path;
            if ($request->query->has('signature')) {
                abort_unless(URL::hasValidSignature($request), 403);
                $parameters = array_merge($request->query(), $route->parameters());
                $expires = Arr::pull($parameters, 'expires');
                unset($parameters['signature']);
                $destination = URL::signedRoute($route->getName(), $parameters, $expires === null ? null : Carbon::createFromTimestamp((int) $expires));
            } elseif ($request->getQueryString()) {
                $destination .= '?'.$request->getQueryString();
            }

            return redirect()->away($destination, $request->query->has('signature') ? 302 : 301)
                ->header('Cache-Control', 'private, no-store');
        }

        $response = $next($request);
        if ($publicHost) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}

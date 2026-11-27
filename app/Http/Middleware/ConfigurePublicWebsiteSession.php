<?php

namespace App\Http\Middleware;

use App\Support\PublicWebsite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ConfigurePublicWebsiteSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = PublicWebsite::origin();
        if ($origin !== null && $request->getHost() === parse_url($origin, PHP_URL_HOST)) {
            URL::useAssetOrigin($origin);
            config([
                'session.cookie' => config('public_website.session_cookie'),
                'session.domain' => null,
                'session.path' => '/',
                'session.secure' => true,
                'session.http_only' => true,
                'session.same_site' => 'lax',
            ]);
        }

        return $next($request);
    }
}

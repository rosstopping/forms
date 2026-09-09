<?php

namespace App\Http\Middleware;

use App\Models\Website;
use App\Support\WebsiteNavigation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentWebsite
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $accessibleWebsites = Website::query()
            ->accessibleTo($user)
            ->with('owner:id,role,membership_tier,admin_membership_tier,admin_membership_expires_at,membership_status,membership_current_period_end')
            ->withExists('forms')
            ->orderBy('name')
            ->get();

        $routeWebsite = $request->route('website');
        $routeWebsiteId = $routeWebsite instanceof Website ? $routeWebsite->id : null;
        $currentWebsite = $accessibleWebsites->firstWhere('id', $routeWebsiteId)
            ?? $accessibleWebsites->firstWhere('id', $user->current_website_id)
            ?? $accessibleWebsites->first();

        if ($currentWebsite && $user->current_website_id !== $currentWebsite->id) {
            $user->forceFill(['current_website_id' => $currentWebsite->id])->saveQuietly();
        }

        $request->attributes->set('currentWebsite', $currentWebsite);

        View::share([
            'navigationWebsites' => $accessibleWebsites,
            'currentWebsite' => $currentWebsite,
            'currentWebsiteSection' => WebsiteNavigation::sectionForRequest($request),
        ]);

        return $next($request);
    }
}

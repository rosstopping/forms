<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictAssignedAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isAdmin() || $user->hasAllWebsiteAccess()) {
            return $next($request);
        }
        abort_if($request->routeIs('admin.users.*', 'admin.impersonation.*', 'admin.onboarding.*', 'admin.prospects.*', 'admin.prospect*',
            'admin.websites.prospect.*', 'admin.websites.service.update', 'admin.websites.create', 'admin.websites.store', 'admin.websites.destroy', 'admin.website-builder.*'), 403);
        abort_if($request->routeIs('admin.websites.update') && $request->has('subscription_user_id'), 403);
        $hasSite = false;
        foreach ($request->route()->parameters() as $parameter) {
            $websiteId = $parameter instanceof Website ? $parameter->id
                : ($parameter instanceof Model ? ($parameter->getAttributes()['website_id'] ?? null) : null);
            if ($websiteId !== null) {
                abort_unless(Website::query()->accessibleTo($user)->whereKey($websiteId)->exists(), 403);
                $hasSite = true;
            }
        }
        if (! $hasSite) {
            abort_unless($request->routeIs('admin.dashboard', 'admin.overview', 'admin.websites.index', 'admin.current-website.update',
                'admin.profile.*', 'admin.billing.index', 'admin.billing.portal', 'admin.forms.index',
                'admin.form-submissions.index', 'admin.form-submissions.create', 'admin.form-submissions.store', 'admin.form-submissions.bulk',
                'admin.search-console.callback', 'admin.github.callback', 'admin.google-ads.callback', 'admin.business-profile.callback'), 403);
        }

        return $next($request);
    }
}

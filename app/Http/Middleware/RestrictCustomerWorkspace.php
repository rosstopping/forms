<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCustomerWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->isMethod('GET') && $request->routeIs('admin.websites.section') && $request->route('section') === 'search') {
                $website = $request->route('website');
                abort_unless($website instanceof Website && $website->isAccessibleBy($request->user()), 403);

                return redirect()->route('admin.search-overview', $website);
            }
            abort_unless($request->routeIs(
                'admin.dashboard',
                'admin.search-overview',
                'admin.weekly-overviews.show',
                'admin.current-website.update',
                'admin.form-submissions.index',
                'admin.form-submissions.show',
                'admin.form-submissions.update',
                'admin.billing.index',
                'admin.billing.portal',
                'admin.profile.edit',
                'admin.profile.update',
                'admin.impersonation.destroy',
            ), 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCustomerWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort_unless($request->routeIs(
                'admin.dashboard',
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

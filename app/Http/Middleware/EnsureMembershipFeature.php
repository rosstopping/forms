<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureMembershipFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        $website = $request->route('website');
        $subscriber = $website instanceof Website ? $website->owner : $user;

        if (! $user?->isAdmin() && ! $subscriber?->hasMembershipFeature($feature)) {
            return Redirect::route('admin.billing.index')
                ->with('error', 'This feature is not included in the website owner’s current package.');
        }

        return $next($request);
    }
}

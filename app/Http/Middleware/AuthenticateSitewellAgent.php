<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSitewellAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $hash = config('services.sitewell_agent.token_hash');
        $token = $request->bearerToken();
        abort_unless(is_string($hash) && strlen($hash) === 64 && is_string($token) && strlen($token) >= 32
            && hash_equals($hash, hash('sha256', $token)), 401, 'Invalid agent credentials.');
        $user = User::query()->find(config('services.sitewell_agent.user_id'));
        abort_unless($user?->isAdmin(), 403, 'Agent access requires an active admin account.');
        $request->setUserResolver(fn (): User => $user);

        return $next($request);
    }
}

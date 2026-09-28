<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GithubConnectionState
{
    /** @param array{website_id: int, stage: string, installation_id?: int} $context */
    public function issue(Request $request, array $context): string
    {
        $state = 'repository_'.Str::random(64);
        Cache::put($this->key($state), [
            ...$context,
            'user_id' => $request->user()->id,
            'session' => hash('sha256', $request->session()->getId()),
        ], now()->addMinutes(15));

        return $state;
    }

    /** @return array{website_id: int, stage: string, installation_id?: int, user_id: int, session: string} */
    public function consume(Request $request, string $state): array
    {
        abort_unless(preg_match('/^repository_[a-zA-Z0-9]{64}$/', $state), 403, 'Restart the GitHub connection.');

        return Cache::lock($this->key($state).':lock', 5)->block(3, function () use ($request, $state): array {
            $context = Cache::get($this->key($state));
            abort_unless(is_array($context)
                && $context['user_id'] === $request->user()->id
                && hash_equals($context['session'], hash('sha256', $request->session()->getId())), 403, 'The GitHub connection has expired or is invalid. Restart the connection.');
            Cache::forget($this->key($state));

            return $context;
        });
    }

    private function key(string $state): string
    {
        return 'github-repository-state:'.hash('sha256', $state);
    }
}

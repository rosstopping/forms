<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\FindMissingServicePages;
use App\Services\MarketingTurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServicePageFinderController extends Controller
{
    public function create(MarketingTurnstileVerifier $turnstile): View
    {
        return view('tools.service-page-finder', ['state' => null, 'turnstileEnabled' => $turnstile->enabled(), 'turnstileSiteKey' => config('services.turnstile.marketing.site_key')]);
    }

    public function store(StoreFreeSiteAuditRequest $request, MarketingTurnstileVerifier $turnstile): RedirectResponse
    {
        if (! $turnstile->passes($request->input('cf-turnstile-response'), $request->ip(), $request->getHost())) {
            throw ValidationException::withMessages(['website_url' => 'Please complete the security check and try again.']);
        }
        $submitted = $request->validated('website_url');
        $url = strtolower((string) parse_url($submitted, PHP_URL_SCHEME)).'://'.strtolower((string) parse_url($submitted, PHP_URL_HOST)).'/';
        $cacheKey = 'service-page-finder-url:'.hash('sha256', $url);
        $lock = Cache::lock($cacheKey.':lock', 10);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['website_url' => 'A check is already starting for this website. Try again in a moment.']);
        }
        try {
            $id = Cache::get($cacheKey);
            $state = is_string($id) ? Cache::get('service-page-finder:'.$id) : null;
            if (! is_array($state) || $state['status'] === 'failed' || (in_array($state['status'], ['queued', 'running'], true) && $state['started_at'] < now()->subMinutes(10)->timestamp)) {
                $id = (string) Str::uuid();
                Cache::put('service-page-finder:'.$id, ['status' => 'queued', 'url' => $url, 'started_at' => now()->timestamp], now()->addDay());
                Cache::put($cacheKey, $id, now()->addDay());
                FindMissingServicePages::dispatch($id, $url);
            }
        } finally {
            $lock->release();
        }

        return redirect()->route('tools.service-page-finder.show', ['id' => $id]);
    }

    public function show(string $id): View
    {
        return view('tools.service-page-finder', ['state' => $this->state($id), 'id' => $id, 'turnstileEnabled' => false]);
    }

    public function status(string $id): JsonResponse
    {
        return response()->json(['status' => $this->state($id)['status']])->header('Cache-Control', 'private, no-store');
    }

    private function state(string $id): array
    {
        $state = Cache::get('service-page-finder:'.$id);
        abort_unless(is_array($state), 404);
        if (in_array($state['status'], ['queued', 'running'], true) && $state['started_at'] < now()->subMinutes(10)->timestamp) {
            $state['status'] = 'failed';
        }

        return $state;
    }
}

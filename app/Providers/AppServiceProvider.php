<?php

namespace App\Providers;

use App\Contracts\SerpProvider;
use App\Models\WebsiteAudit;
use App\Services\CachedSerpProvider;
use App\View\Composers\NavigationComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SerpProvider::class, CachedSerpProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai-visibility', fn ($job) => Limit::perMinute(max(1, (int) config('ai_visibility.checks_per_minute')))->by('ai-visibility:'.$job->result->provider));

        Gate::define('access-outreach', fn ($user): bool => $user->isAdmin());
        RateLimiter::for('website-audits', function (Request $request): array|RedirectResponse {
            $websiteUrl = Str::lower(trim((string) $request->input('website_url')));
            $websiteUrl = Str::startsWith($websiteUrl, ['http://', 'https://']) ? $websiteUrl : 'https://'.$websiteUrl;
            $domain = (string) parse_url($websiteUrl, PHP_URL_HOST);

            $previousAuditId = $request->session()->get('marketing.website_audit_id');
            if (is_string($previousAuditId) && $domain !== '') {
                $previousAudit = WebsiteAudit::query()
                    ->where('public_id', $previousAuditId)
                    ->where('domain', $domain)
                    ->where('expires_at', '>', now())
                    ->whereIn('status', [WebsiteAudit::STATUS_PENDING, WebsiteAudit::STATUS_RUNNING, WebsiteAudit::STATUS_COMPLETED])
                    ->first();

                if ($previousAudit !== null) {
                    return redirect()->route('marketing.website-audits.show', $previousAudit);
                }
            }

            if (app()->environment('local') || $request->user()?->isAdmin()) {
                return [Limit::none()];
            }

            return [
                Limit::perMinute(3)->by('website-audit-minute:'.$request->ip()),
                Limit::perDay(10)->by('website-audit-day:'.$request->ip()),
                Limit::perDay(3)->by('website-audit-domain:'.$domain),
            ];
        });
        RateLimiter::for('website-audit-reports', fn (Request $request): Limit => app()->environment('local') || $request->user()?->isAdmin()
            ? Limit::none()
            : Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('website-audit-status', fn (Request $request): Limit => app()->environment('local') || $request->user()?->isAdmin()
            ? Limit::none()
            : Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('website-audit-email', function (Request $request): array {
            if (app()->environment('local') || $request->user()?->isAdmin()) {
                return [Limit::none()];
            }

            $audit = $request->route('websiteAudit');
            $auditKey = $audit instanceof WebsiteAudit ? $audit->getRouteKey() : (string) $audit;

            return [
                Limit::perMinute(3)->by('website-audit-email-ip:'.$request->ip()),
                Limit::perDay(2)->by('website-audit-email-audit:'.$auditKey),
            ];
        });

        View::composer('layouts.app', NavigationComposer::class);
    }
}

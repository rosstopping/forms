<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingLeadController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'trial' => ['nullable', Rule::in(['active', 'expired', 'converted'])],
            'verification' => ['nullable', Rule::in(['verified', 'pending', 'conflict'])],
            'call' => ['nullable', Rule::in(['not_booked', 'booking_started', 'booked', 'completed'])],
        ]);

        $baseQuery = User::query()->whereHas('websiteAudits', fn (Builder $query) => $query->whereNotNull('claimed_at'));
        $summary = [
            'total' => (clone $baseQuery)->count(),
            'unclaimed' => WebsiteAudit::query()->whereNull('claimed_at')->count(),
            'active' => (clone $baseQuery)->where('onboarding_trial_ends_at', '>', now())->count(),
            'needs_verification' => (clone $baseQuery)->whereHas('websiteAudits.website.domains', fn (Builder $query) => $query->where('is_primary', true)->where('ownership_status', '!=', WebsiteDomain::OWNERSHIP_VERIFIED))->count(),
            'call_not_booked' => (clone $baseQuery)->whereNull('onboarding_call_booked_at')->count(),
        ];

        $users = $baseQuery
            ->with([
                'onboardingAudit.website.domains',
                'onboardingAudit.website.searchConsoleConnection',
                'onboardingLifecycleMessages',
            ])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhereHas('websiteAudits', fn (Builder $query) => $query->where('domain', 'like', $search)));
            })
            ->when(($filters['trial'] ?? null) === 'active', fn (Builder $query) => $query->where('onboarding_trial_ends_at', '>', now()))
            ->when(($filters['trial'] ?? null) === 'expired', fn (Builder $query) => $query->where('onboarding_trial_ends_at', '<=', now())->where('membership_status', '!=', 'active'))
            ->when(($filters['trial'] ?? null) === 'converted', fn (Builder $query) => $query->where('membership_status', 'active'))
            ->when(filled($filters['verification'] ?? null), fn (Builder $query) => $query->whereHas('websiteAudits.website.domains', fn (Builder $query) => $query
                ->where('is_primary', true)
                ->where('ownership_status', $filters['verification'])))
            ->when(($filters['call'] ?? null) === 'not_booked', fn (Builder $query) => $query->whereNull('onboarding_call_booking_started_at')->whereNull('onboarding_call_booked_at'))
            ->when(($filters['call'] ?? null) === 'booking_started', fn (Builder $query) => $query->whereNotNull('onboarding_call_booking_started_at')->whereNull('onboarding_call_booked_at'))
            ->when(($filters['call'] ?? null) === 'booked', fn (Builder $query) => $query->whereNotNull('onboarding_call_booked_at')->whereNull('onboarding_call_completed_at'))
            ->when(($filters['call'] ?? null) === 'completed', fn (Builder $query) => $query->whereNotNull('onboarding_call_completed_at'))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $unclaimedAudits = WebsiteAudit::query()
            ->whereNull('claimed_at')
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn (Builder $query) => $query
                    ->where('domain', 'like', $search)
                    ->orWhere('email', 'like', $search));
            })
            ->latest('created_at')
            ->paginate(20, ['*'], 'unclaimed_page')
            ->withQueryString();

        return view('admin.onboarding-leads.index', compact('filters', 'summary', 'unclaimedAudits', 'users'));
    }
}

@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <header>
        <p class="font-mono text-xs font-medium uppercase tracking-widest text-teal-700">Lead management</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-950 sm:text-3xl">Onboarding</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">Track everyone who has joined Sitewell through Get started, from their submitted domain through verification, trial progress, and onboarding call.</p>
    </header>

    <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><dt class="text-sm text-slate-500">Onboarding leads</dt><dd class="mt-1 text-3xl font-semibold tabular-nums text-slate-950">{{ $summary['total'] }}</dd></div>
        <div class="rounded-xl border border-teal-200 bg-teal-50 p-4"><dt class="text-sm text-teal-800">Active trials</dt><dd class="mt-1 text-3xl font-semibold tabular-nums text-teal-950">{{ $summary['active'] }}</dd></div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><dt class="text-sm text-amber-800">Need verification</dt><dd class="mt-1 text-3xl font-semibold tabular-nums text-amber-950">{{ $summary['needs_verification'] }}</dd></div>
        <div class="rounded-xl border border-violet-200 bg-violet-50 p-4"><dt class="text-sm text-violet-800">Call not booked</dt><dd class="mt-1 text-3xl font-semibold tabular-nums text-violet-950">{{ $summary['call_not_booked'] }}</dd></div>
    </dl>

    <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[minmax(14rem,2fr)_repeat(3,minmax(10rem,1fr))_auto]">
        <label class="sr-only" for="onboarding-search">Search onboarding leads</label>
        <input id="onboarding-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email or domain" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <label class="sr-only" for="onboarding-trial">Trial status</label>
        <select id="onboarding-trial" name="trial" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All trial statuses</option>
            <option value="active" @selected(($filters['trial'] ?? null) === 'active')>Active trial</option>
            <option value="expired" @selected(($filters['trial'] ?? null) === 'expired')>Expired</option>
            <option value="converted" @selected(($filters['trial'] ?? null) === 'converted')>Converted</option>
        </select>
        <label class="sr-only" for="onboarding-verification">Verification status</label>
        <select id="onboarding-verification" name="verification" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All verification</option>
            <option value="verified" @selected(($filters['verification'] ?? null) === 'verified')>Verified</option>
            <option value="pending" @selected(($filters['verification'] ?? null) === 'pending')>Pending</option>
            <option value="conflict" @selected(($filters['verification'] ?? null) === 'conflict')>Conflict</option>
        </select>
        <label class="sr-only" for="onboarding-call">Call status</label>
        <select id="onboarding-call" name="call" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All call statuses</option>
            <option value="not_booked" @selected(($filters['call'] ?? null) === 'not_booked')>Not booked</option>
            <option value="booking_started" @selected(($filters['call'] ?? null) === 'booking_started')>Booking started</option>
            <option value="booked" @selected(($filters['call'] ?? null) === 'booked')>Booked</option>
            <option value="completed" @selected(($filters['call'] ?? null) === 'completed')>Completed</option>
        </select>
        <div class="flex gap-2">
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Filter</button>
            <a href="{{ route('admin.onboarding.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
        </div>
    </form>

    <div class="space-y-4">
        @forelse ($users as $onboardingUser)
            @php
                $audit = $onboardingUser->onboardingAudit;
                $website = $audit?->website;
                $primaryDomain = $website?->domains->firstWhere('is_primary', true) ?? $website?->domains->first();
                $trialEndsAt = $onboardingUser->onboarding_trial_ends_at;
                $isConverted = $onboardingUser->membership_status === 'active';
                $isTrialActive = ! $isConverted && $trialEndsAt?->isFuture();
                $trialDay = $trialEndsAt ? max(1, min(14, (int) floor($trialEndsAt->copy()->subDays(14)->diffInDays(now())) + 1)) : null;
                $daysRemaining = $trialEndsAt?->isFuture() ? max(1, (int) ceil(now()->diffInDays($trialEndsAt))) : 0;
                $callStatus = $onboardingUser->onboarding_call_completed_at ? 'Completed' : ($onboardingUser->onboarding_call_booked_at ? 'Booked' : ($onboardingUser->onboarding_call_booking_started_at ? 'Booking started' : 'Not booked'));
            @endphp
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-5 p-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-950">{{ $onboardingUser->name }}</h2>
                            <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-teal-100 text-teal-800' => $isTrialActive, 'bg-sky-100 text-sky-800' => $isConverted, 'bg-slate-100 text-slate-700' => ! $isTrialActive && ! $isConverted])>{{ $isConverted ? 'Converted' : ($isTrialActive ? 'Active trial' : 'Trial expired') }}</span>
                        </div>
                        <a href="mailto:{{ $onboardingUser->email }}" class="mt-1 inline-flex text-sm text-slate-600 hover:text-teal-700">{{ $onboardingUser->email }}</a>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @forelse ($website?->domains ?? collect() as $domain)
                                <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 font-mono text-xs text-slate-700">{{ $domain->domain }}</span>
                            @empty
                                @if ($audit?->domain)<span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 font-mono text-xs text-slate-700">{{ $audit->domain }}</span>@endif
                            @endforelse
                        </div>
                    </div>

                    <dl class="grid min-w-0 gap-4 sm:grid-cols-3 xl:w-[46rem]">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Trial progress</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $isConverted ? 'Paid membership' : ($trialDay ? 'Day '.$trialDay.' of 14' : 'Dates unavailable') }}</dd>
                            @if ($isTrialActive)<p class="mt-1 text-xs text-slate-500">{{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }} remaining</p>@elseif ($trialEndsAt && ! $isConverted)<p class="mt-1 text-xs text-red-600">Ended {{ $trialEndsAt->diffForHumans() }}</p>@endif
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Website verification</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $primaryDomain?->isVerified() ? 'text-emerald-700' : ($primaryDomain?->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT ? 'text-red-700' : 'text-amber-700') }}">{{ $primaryDomain?->isVerified() ? 'Verified' : ($primaryDomain?->ownership_status === \App\Models\WebsiteDomain::OWNERSHIP_CONFLICT ? 'Conflict' : 'Pending') }}</dd>
                            <p class="mt-1 text-xs text-slate-500">{{ $primaryDomain?->verification_method === 'search_console' ? 'Via Search Console' : ($website?->searchConsoleConnection?->property_url ? 'Search Console connected' : 'Search Console not connected') }}</p>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Onboarding call</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $onboardingUser->onboarding_call_booked_at ? 'text-emerald-700' : 'text-violet-700' }}">{{ $callStatus }}</dd>
                            <form method="POST" action="{{ route('admin.users.onboarding-call.update', $onboardingUser) }}" class="mt-2">
                                @csrf
                                @method('PATCH')
                                <label for="call-status-{{ $onboardingUser->id }}" class="sr-only">Call status for {{ $onboardingUser->name }}</label>
                                <select id="call-status-{{ $onboardingUser->id }}" name="status" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs">
                                    <option value="not_booked" @selected(! $onboardingUser->onboarding_call_booked_at)>Not booked</option>
                                    <option value="booked" @selected($onboardingUser->onboarding_call_booked_at && ! $onboardingUser->onboarding_call_completed_at)>Booked</option>
                                    <option value="completed" @selected($onboardingUser->onboarding_call_completed_at)>Completed</option>
                                </select>
                            </form>
                        </div>
                    </dl>
                </div>
                <footer class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 bg-slate-50 px-5 py-3 text-sm">
                    @if ($website)<a href="{{ route('admin.websites.show', $website) }}" class="font-semibold text-teal-700 hover:text-teal-900">Open website</a>@endif
                    <a href="{{ route('admin.users.edit', $onboardingUser) }}" class="font-medium text-slate-600 hover:text-slate-950">Account details</a>
                    @if ($onboardingUser->canBeImpersonated())
                        <form method="POST" action="{{ route('admin.users.impersonate.store', $onboardingUser) }}">@csrf<button class="font-medium text-slate-600 hover:text-slate-950">View as user</button></form>
                    @endif
                    <span class="ml-auto text-xs text-slate-500">Signed up {{ $audit?->claimed_at?->diffForHumans() ?? $onboardingUser->created_at?->diffForHumans() }}</span>
                </footer>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <h2 class="font-semibold text-slate-900">No onboarding leads found</h2>
                <p class="mt-1 text-sm text-slate-600">Try clearing the filters, or wait for the next Get started signup.</p>
            </div>
        @endforelse
    </div>

    {{ $users->links() }}
</div>
@endsection

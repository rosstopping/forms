@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-medium text-teal-700">Account</p><h1 class="text-2xl font-semibold text-slate-950">Billing and membership</h1><p class="mt-1 max-w-2xl text-sm text-slate-600">Choose the level of website care that fits your business. Stripe securely handles payment details, invoices, package changes, and cancellation.</p></div>
        @if ($user->stripe_customer_id)<form method="POST" action="{{ route('admin.billing.portal') }}">@csrf<button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Manage membership in Stripe</button></form>@endif
    </div>

    @if (session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if (request('checkout') === 'success')<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Payment received. Stripe is confirming your membership; this page will update as soon as the webhook arrives.</div>@elseif (request('checkout') === 'cancelled')<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Checkout was cancelled and your membership was not changed.</div>@endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="membership-status-title">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Current membership</p><h2 id="membership-status-title" class="mt-1 text-lg font-semibold text-slate-950">{{ data_get($plans, $user->effectiveMembershipTier().'.name', 'No active package') }}</h2><p class="mt-1 text-sm text-slate-600">Status: <span class="font-medium capitalize">{{ $user->hasAdminManagedMembership() ? 'admin managed' : str_replace('_', ' ', $user->membership_status ?: 'not subscribed') }}</span>@if (! $user->hasAdminManagedMembership() && $user->membership_current_period_end) · Current period ends {{ $user->membership_current_period_end->format('j F Y') }}@endif</p>@if (! $user->hasAdminManagedMembership() && $user->membership_cancel_at)<p class="mt-1 text-sm font-medium text-amber-700">Cancellation is scheduled for {{ $user->membership_cancel_at->format('j F Y') }}.</p>@endif</div>
            @if ($user->stripe_customer_id)<p class="text-xs text-slate-500">Package changes and cancellation open securely on Stripe.</p>@endif
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-3">
        @foreach ($plans as $tier => $plan)
            @php($current = $user->effectiveMembershipTier() === $tier && $user->hasActiveMembership())
            <article @class(['flex flex-col rounded-xl border bg-white p-6 shadow-sm', 'border-teal-500 ring-2 ring-teal-100' => $tier === 'growth', 'border-slate-200' => $tier !== 'growth'])>
                <div class="flex items-center justify-between gap-3"><h2 class="text-xl font-semibold text-slate-950">{{ $plan['name'] }}</h2>@if ($tier === 'growth')<span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800">Most popular</span>@endif</div>
                <p class="mt-3 min-h-12 text-sm leading-6 text-slate-600">{{ $plan['description'] }}</p>
                <p class="mt-6"><span class="text-4xl font-semibold tracking-tight text-slate-950">£{{ $plan['price'] }}</span><span class="text-sm text-slate-500">/month</span></p>
                <p class="mt-3 text-sm font-semibold text-slate-700">{{ $plan['summary'] }}</p>
                <ul class="mt-5 grid gap-3 text-sm leading-5 text-slate-600" role="list">@foreach ($plan['features'] as $feature)<li class="flex gap-2"><span class="text-teal-600" aria-hidden="true">✓</span><span>{{ $feature }}</span></li>@endforeach</ul>
                <form method="POST" action="{{ $user->hasActiveMembership() && $user->stripe_customer_id ? route('admin.billing.portal') : route('admin.billing.checkout') }}" class="mt-auto pt-7">
                    @csrf
                    @unless ($user->hasActiveMembership() && $user->stripe_customer_id)<input type="hidden" name="tier" value="{{ $tier }}">@endunless
                    <button type="submit" @disabled($current) @class(['w-full rounded-md px-4 py-2.5 text-sm font-semibold', 'cursor-default bg-slate-100 text-slate-500' => $current, 'bg-slate-950 text-white hover:bg-slate-800' => ! $current])>{{ $current ? 'Current package' : ($user->stripe_customer_id ? 'Change with Stripe' : 'Choose '.$plan['name']) }}</button>
                </form>
            </article>
        @endforeach
    </div>

    <p class="text-sm text-slate-500">Prices exclude VAT. Every package is designed around one business website. Features not specifically assigned to Growth or Complete are available across all packages.</p>
</div>
@endsection

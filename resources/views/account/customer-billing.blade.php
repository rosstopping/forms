@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <x-portal-notices :validation-errors="session('errors', $errors)" />
    <header><h1 class="text-2xl font-semibold">Billing</h1><p class="mt-2 text-sm text-slate-600">Your invoices, payment details and agreed website service.</p></header>
    <section class="ui-panel ui-section"><h2 class="font-semibold">Website service</h2>
        @if($currentWebsite)<p class="mt-3 text-sm text-slate-600">{{ $currentWebsite->name }} · {{ data_get($plans, $currentWebsite->service_package.'.name', 'Service being arranged') }}</p><p class="mt-2 text-sm text-slate-600">Service status: {{ ucfirst($currentWebsite->service_status ?? 'Not assigned') }}</p>@else<p class="mt-3 text-sm text-slate-600">No website service is assigned to your account yet.</p>@endif
        <p class="mt-3 text-sm text-slate-500">Please contact the Sitewell team to discuss changes to your service.</p>
    </section>
    <section class="ui-panel ui-section"><h2 class="font-semibold">Invoices and payments</h2>
        @if($user->stripe_customer_id)
            <p class="mt-3 text-sm text-slate-600">Your billing details are held securely by Stripe.</p>
            @if(is_impersonating())<p class="mt-3 text-sm text-amber-800">Payment details cannot be changed while viewing the account as this customer.</p>@else<form method="POST" action="{{ route('admin.billing.portal') }}" class="mt-4">@csrf<button class="ui-button ui-button-secondary">View invoices and payment details</button></form>@endif
        @else<p class="mt-3 text-sm text-slate-600">Online billing has not been set up for this account yet.</p>@endif
    </section>
</div>
@endsection

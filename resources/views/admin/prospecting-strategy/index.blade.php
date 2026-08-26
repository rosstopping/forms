@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Outreach automation</p><h1 class="text-3xl font-semibold tracking-tight">Prospecting strategy</h1><p class="mt-1 text-sm text-slate-600">Manage the industries and locations used by automatic prospecting in Outreach.</p></div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('admin.prospecting-locations.create') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Add location</a><a href="{{ route('admin.prospecting-industry-profiles.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Add industry</a></div>
    </div>

    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif

    <section class="space-y-3">
        <div><h2 class="text-xl font-semibold">Prospecting industries</h2><p class="mt-1 text-sm text-slate-600">Results use existing prospect and outreach lifecycle data.</p></div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Industry</th><th class="px-4 py-3">Strategy</th><th class="px-4 py-3">Ranking / size</th><th class="px-4 py-3">Results</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse ($profiles as $profile)
                    <tr><td class="px-4 py-4 align-top"><div class="font-semibold text-slate-950">{{ $profile->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $profile->enabled ? 'Enabled' : 'Disabled' }} · priority {{ $profile->priority }}</div></td><td class="max-w-md px-4 py-4 align-top"><div>£{{ number_format($profile->estimated_customer_value) }} estimated customer value</div><div class="mt-1 text-xs text-slate-500">{{ implode(' · ', array_slice($profile->search_keywords, 0, 4)) }}</div></td><td class="whitespace-nowrap px-4 py-4 align-top">Positions {{ $profile->minimum_position }}–{{ $profile->maximum_position }}<div class="mt-1 text-xs text-slate-500">Up to {{ $profile->maximum_site_size }} pages · import {{ $profile->automatic_import_score }}+</div></td><td class="whitespace-nowrap px-4 py-4 align-top">{{ $profile->prospects_count }} found · {{ $profile->approved_prospects_count }} approved<div class="mt-1 text-xs text-slate-500">{{ $profile->emailed_prospects_count }} sent · {{ $profile->opens_count }} opens · {{ $profile->clicks_count }} clicks · {{ $profile->replied_prospects_count }} replies</div><div class="mt-1 text-xs text-slate-500">{{ $profile->warm_prospects_count }} warm · {{ $profile->hot_prospects_count }} hot · {{ $profile->customers_count }} customers · {{ $profile->prospects_count > 0 ? number_format(($profile->customers_count / $profile->prospects_count) * 100, 1) : '0.0' }}%</div></td><td class="px-4 py-4 text-right align-top"><a href="{{ route('admin.prospecting-industry-profiles.edit', $profile) }}" class="font-semibold text-teal-700 hover:text-teal-900">Edit</a></td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No industry profiles configured.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </section>

    <section class="space-y-3">
        <div><h2 class="text-xl font-semibold">Prospecting locations</h2><p class="mt-1 text-sm text-slate-600">Enabled locations are paired with industry profiles in priority order.</p></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($locations as $location)<a href="{{ route('admin.prospecting-locations.edit', $location) }}" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:bg-slate-50"><span><span class="font-semibold">{{ $location->name }}</span><span class="mt-1 block text-xs text-slate-500">{{ $location->enabled ? 'Enabled' : 'Disabled' }}</span></span><span class="text-sm text-slate-500">Priority {{ $location->priority }}</span></a>@endforeach
        </div>
    </section>
</div>
@endsection

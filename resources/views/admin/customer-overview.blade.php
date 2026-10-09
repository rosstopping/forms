@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <x-portal-notices :validation-errors="session('errors', $errors)" />
    <header><h1 class="text-2xl font-semibold text-slate-950">{{ $website?->name ?? 'Your website overview' }}</h1><p class="mt-2 text-sm text-slate-600">Your website’s progress, completed work and latest reports.</p></header>
    @if($website)
        <x-seo-progress-timeline :items="$progressTimeline" />
        <x-weekly-overview :report="$weeklyOverview" :history="$weeklyHistory" />
        <section class="ui-panel ui-section @container"><h2 class="font-semibold">Google Search Console</h2><p class="mt-1 text-sm text-slate-600">Recorded organic search performance for your website.</p>
            @if($website->searchConsoleConnection?->property_url)<x-search-progress :report="$searchProgress" />@else<p class="mt-4 text-sm text-slate-500">Search performance will appear here when reporting is available.</p>@endif
        </section>
    @else
        <section class="ui-panel ui-section"><p class="text-sm text-slate-600">No website is assigned to your account yet. Please contact the Sitewell team.</p></section>
    @endif
</div>
@endsection

@extends('layouts.app')

@section('content')
    <div class="space-y-8">
        <header>
            <a href="{{ route('admin.search-console.performance', $website) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to Search performance</a>
            <p class="mt-6 text-xs font-medium uppercase tracking-wide text-slate-500">Google Search Console query</p>
            <h1 class="mt-1 text-balance text-2xl font-semibold tracking-tight text-slate-950">{{ $query }}</h1>
            <p class="mt-2 text-pretty text-base text-slate-600 sm:text-sm">First-party Google performance for this exact search query. Search Console may omit anonymised or very low-volume data.</p>
        </header>

        <section aria-labelledby="query-performance-heading">
            <h2 id="query-performance-heading" class="text-lg font-semibold text-slate-950">Clicks vs impressions over time</h2>
            <p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">Clicks are visits from Google; impressions are appearances in search results.</p>
            <div class="mt-4 grid gap-4">
                <x-comparison-chart title="Clicks and impressions" description="Actual clicks for this query compared with its search-result appearances." :points="$history" first-key="clicks" first-label="Clicks" second-key="impressions" second-label="Impressions" />
                <x-progress-chart title="Click-through rate" description="The percentage of impressions that became clicks." :points="collect($history)->map(fn ($point) => [...$point, 'ctr_percentage' => $point['ctr'] * 100])" value-key="ctr_percentage" format="percentage" />
                <x-progress-chart title="Average position" description="Impression-weighted average position; lower is better." :points="$history" value-key="position" format="decimal" :lower-is-better="true" />
            </div>
        </section>
    </div>
@endsection

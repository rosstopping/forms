@extends('layouts.app')

@section('content')
    <div class="space-y-8">
        <header>
            <a href="{{ route('admin.websites.show', $website) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to {{ $website->name }}</a>
            <p class="mt-6 text-xs font-medium uppercase tracking-wide text-slate-500">Google Search Console</p>
            <div class="mt-1 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-balance text-2xl font-semibold tracking-tight text-slate-950">Search performance</h1>
                    <p class="mt-2 break-all text-sm text-slate-600">{{ $connection->property_url }}</p>
                </div>
                <p class="text-sm text-slate-500">{{ $period['start']->format('j M') }}–{{ $period['end']->format('j M Y') }}</p>
            </div>
            <p class="mt-4 rounded-md bg-slate-100 px-4 py-3 text-sm text-slate-600">Position is the average position of your highest result for each search. Results are ordered by clicks and Search Console may limit the data it returns.</p>
        </header>

        <section aria-labelledby="queries-heading">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-200 pb-3">
                <div>
                    <h2 id="queries-heading" class="text-lg font-semibold text-slate-950">All available queries</h2>
                    <p class="mt-1 text-sm text-slate-500">The searches people used to find this website.</p>
                </div>
                @if ($queries)
                    <p class="text-sm tabular-nums text-slate-500">Rows {{ number_format((($queryPage - 1) * $pageSize) + 1) }}–{{ number_format((($queryPage - 1) * $pageSize) + count($queries)) }}</p>
                @endif
            </div>
            <div class="-mx-4 overflow-x-auto whitespace-nowrap">
                <div class="inline-block min-w-full px-4 align-middle">
                    <table class="w-full divide-y divide-slate-950/10 text-sm">
                        <thead><tr class="text-left text-slate-500"><th class="py-3 pr-6 font-medium">Query</th><th class="px-3 py-3 text-right font-medium">Clicks</th><th class="px-3 py-3 text-right font-medium">Impressions</th><th class="px-3 py-3 text-right font-medium">CTR</th><th class="py-3 pl-3 text-right font-medium">Average position</th></tr></thead>
                        <tbody class="divide-y divide-slate-950/5">
                            @forelse ($queries as $query)
                                <tr><td class="max-w-96 truncate py-3 pr-6 font-medium text-slate-900" title="{{ $query['query'] }}">{{ $query['query'] }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['clicks']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['impressions']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['ctr'] * 100, 1) }}%</td><td class="py-3 pl-3 text-right tabular-nums">{{ number_format($query['position'], 1) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">No query data is available for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <nav class="mt-4 flex items-center justify-between" aria-label="Query results pages">
                @if ($queryPage > 1)<a href="{{ route('admin.search-console.performance', [$website, 'queries_page' => $queryPage - 1, 'pages_page' => $pagePage]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>@else<span></span>@endif
                @if ($hasMoreQueries)<a href="{{ route('admin.search-console.performance', [$website, 'queries_page' => $queryPage + 1, 'pages_page' => $pagePage]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>@endif
            </nav>
        </section>

        <section aria-labelledby="pages-heading">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-200 pb-3">
                <div><h2 id="pages-heading" class="text-lg font-semibold text-slate-950">All available landing pages</h2><p class="mt-1 text-sm text-slate-500">The pages that appeared in Google search results.</p></div>
                @if ($landingPages)<p class="text-sm tabular-nums text-slate-500">Rows {{ number_format((($pagePage - 1) * $pageSize) + 1) }}–{{ number_format((($pagePage - 1) * $pageSize) + count($landingPages)) }}</p>@endif
            </div>
            <div class="-mx-4 overflow-x-auto whitespace-nowrap"><div class="inline-block min-w-full px-4 align-middle">
                <table class="w-full divide-y divide-slate-950/10 text-sm">
                    <thead><tr class="text-left text-slate-500"><th class="py-3 pr-6 font-medium">Landing page</th><th class="px-3 py-3 text-right font-medium">Clicks</th><th class="px-3 py-3 text-right font-medium">Impressions</th><th class="px-3 py-3 text-right font-medium">CTR</th><th class="py-3 pl-3 text-right font-medium">Average position</th></tr></thead>
                    <tbody class="divide-y divide-slate-950/5">
                        @forelse ($landingPages as $page)
                            <tr><td class="max-w-96 truncate py-3 pr-6"><a href="{{ $page['page'] }}" target="_blank" rel="noreferrer" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900" title="{{ $page['page'] }}">{{ \Illuminate\Support\Str::after($page['page'], '://') }}</a></td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($page['clicks']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($page['impressions']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($page['ctr'] * 100, 1) }}%</td><td class="py-3 pl-3 text-right tabular-nums">{{ number_format($page['position'], 1) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">No landing page data is available for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
            <nav class="mt-4 flex items-center justify-between" aria-label="Landing page results pages">
                @if ($pagePage > 1)<a href="{{ route('admin.search-console.performance', [$website, 'queries_page' => $queryPage, 'pages_page' => $pagePage - 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>@else<span></span>@endif
                @if ($hasMorePages)<a href="{{ route('admin.search-console.performance', [$website, 'queries_page' => $queryPage, 'pages_page' => $pagePage + 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>@endif
            </nav>
        </section>
    </div>
@endsection

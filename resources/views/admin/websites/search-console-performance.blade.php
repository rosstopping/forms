@extends('layouts.app')

@section('content')
    @php
        $performanceUrl = fn (array $parameters): string => route('admin.search-console.performance', [$website, ...array_merge(request()->query(), $parameters)]);
        $nextQueryDirection = fn (string $column): string => $querySort === $column && $queryDirection === 'asc' ? 'desc' : 'asc';
        $nextPageDirection = fn (string $column): string => $pageSort === $column && $pageDirection === 'asc' ? 'desc' : 'asc';
        $sortIndicator = fn (string $activeColumn, string $column, string $direction): string => $activeColumn === $column ? ($direction === 'asc' ? '↑' : '↓') : '↕';
    @endphp
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

        <div class="grid gap-4 lg:grid-cols-2">
            <x-progress-chart title="Organic clicks" description="Actual monthly clicks from Google Search Console." :points="$history" value-key="clicks" />
            <x-progress-chart title="Search impressions" description="Monthly appearances in Google search results." :points="$history" value-key="impressions" />
            <x-progress-chart title="Average CTR" description="Monthly click-through rate." :points="collect($history)->map(fn ($point) => [...$point, 'ctr_percentage' => $point['ctr'] * 100])" value-key="ctr_percentage" format="percentage" />
            <x-progress-chart title="Average position" description="Impression-weighted average position; lower is better." :points="$history" value-key="position" format="decimal" :lower-is-better="true" />
        </div>

        <section aria-labelledby="queries-heading">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-200 pb-3">
                <div>
                    <h2 id="queries-heading" class="text-lg font-semibold text-slate-950">All available queries</h2>
                    <p class="mt-1 text-sm text-slate-500">The searches people used and the page that ranked for each one. Select a query to see its history.</p>
                </div>
                @if ($queries)
                    <p class="text-sm tabular-nums text-slate-500">Rows {{ number_format((($queryPage - 1) * $pageSize) + 1) }}–{{ number_format((($queryPage - 1) * $pageSize) + count($queries)) }}</p>
                @endif
            </div>
            <div class="-mx-4 overflow-x-auto whitespace-nowrap">
                <div class="inline-block min-w-full px-4 align-middle">
                    <table class="w-full divide-y divide-slate-950/10 text-sm">
                        <thead><tr class="text-left text-slate-500">
                            <th class="py-3 pr-6 font-medium" aria-sort="{{ $querySort === 'query' ? ($queryDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $performanceUrl(['query_sort' => 'query', 'query_direction' => $nextQueryDirection('query'), 'queries_page' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-900">Query <span aria-hidden="true">{{ $sortIndicator($querySort, 'query', $queryDirection) }}</span></a></th>
                            <th class="px-3 py-3 font-medium" aria-sort="{{ $querySort === 'page' ? ($queryDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $performanceUrl(['query_sort' => 'page', 'query_direction' => $nextQueryDirection('page'), 'queries_page' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-900">Ranking page <span aria-hidden="true">{{ $sortIndicator($querySort, 'page', $queryDirection) }}</span></a></th>
                            @foreach (['clicks' => 'Clicks', 'impressions' => 'Impressions', 'ctr' => 'CTR', 'position' => 'Average position'] as $column => $label)
                                <th class="px-3 py-3 text-right font-medium last:pr-0" aria-sort="{{ $querySort === $column ? ($queryDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $performanceUrl(['query_sort' => $column, 'query_direction' => $nextQueryDirection($column), 'queries_page' => 1]) }}" class="inline-flex items-center justify-end gap-1 hover:text-slate-900">{{ $label }} <span aria-hidden="true">{{ $sortIndicator($querySort, $column, $queryDirection) }}</span></a></th>
                            @endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-slate-950/5">
                            @forelse ($queries as $query)
                                <tr><td class="max-w-96 truncate py-3 pr-6 font-medium" title="{{ $query['query'] }}"><a href="{{ route('admin.search-console.queries.show', [$website, 'query' => $query['query']]) }}" class="text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">{{ $query['query'] }}</a></td><td class="max-w-96 truncate px-3 py-3"><a href="{{ $query['page'] }}" target="_blank" rel="noreferrer" class="text-slate-700 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900" title="{{ $query['page'] }}">{{ \Illuminate\Support\Str::after($query['page'], '://') }}</a></td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['clicks']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['impressions']) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ number_format($query['ctr'] * 100, 1) }}%</td><td class="py-3 pl-3 text-right tabular-nums">{{ number_format($query['position'], 1) }}</td></tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-slate-500">No query data is available for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <nav class="mt-4 flex items-center justify-between" aria-label="Query results pages">
                @if ($queryPage > 1)<a href="{{ $performanceUrl(['queries_page' => $queryPage - 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>@else<span></span>@endif
                @if ($hasMoreQueries)<a href="{{ $performanceUrl(['queries_page' => $queryPage + 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>@endif
            </nav>
        </section>

        <section aria-labelledby="pages-heading">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-200 pb-3">
                <div><h2 id="pages-heading" class="text-lg font-semibold text-slate-950">All available landing pages</h2><p class="mt-1 text-sm text-slate-500">The pages that appeared in Google search results.</p></div>
                @if ($landingPages)<p class="text-sm tabular-nums text-slate-500">Rows {{ number_format((($pagePage - 1) * $pageSize) + 1) }}–{{ number_format((($pagePage - 1) * $pageSize) + count($landingPages)) }}</p>@endif
            </div>
            <div class="-mx-4 overflow-x-auto whitespace-nowrap"><div class="inline-block min-w-full px-4 align-middle">
                <table class="w-full divide-y divide-slate-950/10 text-sm">
                    <thead><tr class="text-left text-slate-500">
                        <th class="py-3 pr-6 font-medium" aria-sort="{{ $pageSort === 'page' ? ($pageDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $performanceUrl(['page_sort' => 'page', 'page_direction' => $nextPageDirection('page'), 'pages_page' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-900">Landing page <span aria-hidden="true">{{ $sortIndicator($pageSort, 'page', $pageDirection) }}</span></a></th>
                        @foreach (['clicks' => 'Clicks', 'impressions' => 'Impressions', 'ctr' => 'CTR', 'position' => 'Average position'] as $column => $label)
                            <th class="px-3 py-3 text-right font-medium last:pr-0" aria-sort="{{ $pageSort === $column ? ($pageDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ $performanceUrl(['page_sort' => $column, 'page_direction' => $nextPageDirection($column), 'pages_page' => 1]) }}" class="inline-flex items-center justify-end gap-1 hover:text-slate-900">{{ $label }} <span aria-hidden="true">{{ $sortIndicator($pageSort, $column, $pageDirection) }}</span></a></th>
                        @endforeach
                    </tr></thead>
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
                @if ($pagePage > 1)<a href="{{ $performanceUrl(['pages_page' => $pagePage - 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>@else<span></span>@endif
                @if ($hasMorePages)<a href="{{ $performanceUrl(['pages_page' => $pagePage + 1]) }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>@endif
            </nav>
        </section>
    </div>
@endsection

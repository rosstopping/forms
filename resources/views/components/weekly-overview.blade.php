@props(['report', 'history'])

<section class="ui-panel ui-section border-teal-200 sm:p-7" aria-labelledby="weekly-overview-heading">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-mono text-teal-700 text-base sm:text-sm">Your week with Sitewell</p>
            <h2 id="weekly-overview-heading" class="mt-1 text-xl font-semibold text-slate-950">Weekly Overview</h2>
            @if ($report)
                <p class="mt-2 text-slate-500 text-base sm:text-sm">{{ $report->period_start->format('j M') }}–{{ $report->period_end->format('j M Y') }}</p>
            @endif
        </div>
        @if ($history->isNotEmpty())
            <details class="max-w-full text-sm">
                <summary class="cursor-pointer font-medium text-teal-700">Previous overviews</summary>
                <ul class="mt-3 space-y-2">
                    @foreach ($history as $week)
                        <li><a href="{{ route('admin.weekly-overviews.show', ['website' => $report->website_id, 'weekly_report' => $week->id]) }}" class="text-slate-600 underline decoration-slate-300 underline-offset-4 hover:text-teal-700">{{ $week->period_start->format('j M') }}–{{ $week->period_end->format('j M Y') }}</a></li>
                    @endforeach
                </ul>
                {{ $history->links() }}
            </details>
        @endif
    </div>
    @if (! $report)
        <p class="mt-4 leading-6 text-slate-600 text-base sm:text-sm">Your first Weekly Overview will appear here when the next enabled weekly report is prepared.</p>
    @else
        <div class="mt-5 max-w-4xl space-y-3 text-base leading-7 text-slate-700">
            @foreach (preg_split('/\n\s*\n/', $report->overview) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($report->snapshot['metric_cards'] ?? [] as $metric)
                <div class="ui-well p-4">
                    <p class="text-slate-600 text-base sm:text-sm">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $metric['current'] === null ? '—' : number_format($metric['current'], 1) }}</p>
                    <p @class(['mt-1 text-xs', 'text-teal-700' => $metric['direction'] === 'positive', 'text-rose-700' => $metric['direction'] === 'negative', 'text-slate-500' => $metric['direction'] === 'neutral'])>
                        @if ($metric['change'] === null)
                            {{ $metric['current'] === null ? 'Data unavailable' : 'No comparable previous period' }}
                        @else
                            {{ $metric['change'] > 0 ? '+' : '' }}{{ $metric['change'] }}@if ($metric['percent'] !== null) ({{ $metric['percent'] }}%)@endif · {{ $metric['direction'] === 'positive' ? 'Improved' : ($metric['direction'] === 'negative' ? 'Declined' : 'Unchanged') }}
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
        <p class="mt-3 leading-5 text-slate-500 text-base sm:text-sm">Search metrics use the dated week below to allow for reporting delays. Average tracked position includes only keywords found in Google. Site health shows the percentage of recorded checks passing.</p>
        <details class="mt-6 border-t border-slate-950/10 pt-5">
            <summary class="cursor-pointer text-sm font-semibold text-teal-700">Explore this week’s details</summary>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                @foreach ($report->snapshot['sections'] ?? [] as $sourceKey => $section)
                    <div class="min-w-0 break-words">
                        <h3 class="font-semibold text-slate-950">{{ $section['title'] }}</h3>
                        <p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">{{ $section['summary'] }}</p>
                        @if (data_get($report->snapshot, $sourceKey.'.metrics') || ($sourceKey === 'google_business' && data_get($report->snapshot, 'google_business.reviews')))
                            <dl class="mt-3 space-y-2 text-sm">
                                @foreach (array_merge(data_get($report->snapshot, $sourceKey.'.metrics', []), $sourceKey === 'google_business' ? data_get($report->snapshot, 'google_business.reviews', []) : []) as $metric)
                                    <div class="flex flex-wrap justify-between gap-2"><dt class="text-slate-600">{{ $metric['label'] }}</dt><dd class="text-slate-900">{{ $metric['current'] === null ? 'Unavailable' : number_format($metric['current'], 1) }}@if ($metric['change'] !== null) · {{ $metric['change'] > 0 ? '+' : '' }}{{ $metric['change'] }}@endif</dd></div>
                                @endforeach
                            </dl>
                        @endif
                        @if ($sourceKey === 'rankings')
                            <p class="mt-3 text-slate-600 text-base sm:text-sm">Top 3: {{ data_get($report->snapshot, 'rankings.top_3', 0) }} · Positions 4–10: {{ data_get($report->snapshot, 'rankings.positions_4_10', 0) }} · Positions 11–20: {{ data_get($report->snapshot, 'rankings.positions_11_20', 0) }} · Outside top 20: {{ data_get($report->snapshot, 'rankings.outside_top_20', 0) }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                @foreach (data_get($report->snapshot, 'rankings.movements', []) as $movement)
                                    <li><strong>{{ $movement['term'] }}</strong>: {{ $movement['previous'] ?? 'Outside top 100' }} → {{ $movement['current'] ?? 'Outside top 100' }}. {{ implode('; ', $movement['crossings']) }}</li>
                                @endforeach
                            </ul>
                        @elseif ($sourceKey === 'search_console')
                            @foreach (['queries' => 'Queries', 'pages' => 'Pages'] as $dimension => $label)
                                @foreach (['gaining' => 'gaining visibility', 'losing' => 'losing visibility'] as $direction => $description)
                                    @if (data_get($report->snapshot, 'search_console.'.$dimension.'.'.$direction))
                                        <h4 class="mt-3 text-sm font-medium text-slate-900">{{ $label }} {{ $description }}</h4>
                                        <ul class="mt-1 space-y-2 break-words text-sm text-slate-600">
                                            @foreach (data_get($report->snapshot, 'search_console.'.$dimension.'.'.$direction, []) as $movement)
                                                <li>{{ $movement['key'] }}: {{ $movement['impressions']['change'] > 0 ? '+' : '' }}{{ $movement['impressions']['change'] }} impressions, {{ $movement['clicks']['change'] > 0 ? '+' : '' }}{{ $movement['clicks']['change'] }} clicks.</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endforeach
                            @endforeach
                        @elseif ($sourceKey === 'site_audit')
                            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                                @foreach (data_get($report->snapshot, 'site_audit.findings', []) as $finding)
                                    <li><strong>{{ $finding['label'] }}</strong>: {{ $finding['message'] ?? 'Needs attention.' }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ $section['url'] }}" class="mt-2 inline-block text-sm text-teal-700 underline underline-offset-4">View current details</a>
                    </div>
                @endforeach
                <div>
                    <h3 class="font-semibold text-slate-950">Work completed by Sitewell</h3>
                    <ul class="mt-2 space-y-2 text-sm leading-6 text-slate-600">
                        @forelse ($report->snapshot['completed_work'] ?? [] as $work)
                            <li><a href="{{ $work['url'] }}" class="underline decoration-slate-300 underline-offset-4">{{ $work['title'] }}</a></li>
                        @empty
                            <li>No completed work was recorded during this period.</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-950">Recommended next priority</h3>
                    <p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">{{ $report->recommended_priority ? $report->recommended_priority['title'].'. '.$report->recommended_priority['reason'] : 'There is not enough evidence to recommend a specific next priority yet.' }}</p>
                </div>
            </div>
        </details>
    @endif
</section>

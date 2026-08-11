@props(['report'])

@php($pageSpeedResults = collect(data_get($report->metrics, 'pagespeed', [])))

@if ($pageSpeedResults->isNotEmpty())
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="performance-summary-title">
        <div class="border-b border-slate-200 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Google PageSpeed Insights</p>
            <h2 id="performance-summary-title" class="mt-1 font-semibold text-slate-950">Page speed and Core Web Vitals</h2>
            <p class="mt-1 text-sm text-slate-600">Field data reflects real Chrome users over the previous 28 days when enough traffic is available. Lab data is a simulated test from this report run.</p>
        </div>

        <div class="divide-y divide-slate-200">
            @foreach ($pageSpeedResults->groupBy('url') as $url => $strategies)
                <article class="p-4">
                    <p class="truncate text-sm font-medium text-slate-950" title="{{ $url }}">{{ $url }}</p>
                    <div class="mt-3 grid gap-4 lg:grid-cols-2">
                        @foreach ($strategies as $result)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold capitalize text-slate-900">{{ $result['strategy'] }}</h3>
                                    @if ($result['available'])
                                        <span @class([
                                            'rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums',
                                            'bg-emerald-100 text-emerald-800' => $result['performance_score'] >= 90,
                                            'bg-amber-100 text-amber-800' => $result['performance_score'] >= 50 && $result['performance_score'] < 90,
                                            'bg-red-100 text-red-800' => $result['performance_score'] < 50,
                                        ])>{{ $result['performance_score'] }}/100 lab score</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">Temporarily unavailable</span>
                                    @endif
                                </div>

                                @if ($result['available'])
                                    @if (data_get($result, 'field.available'))
                                        <div class="mt-4">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Real-user Core Web Vitals</p>
                                            <dl class="mt-2 grid grid-cols-3 gap-2 text-sm">
                                                @foreach ([['LCP', 'lcp_ms', ' ms', 'lcp_status'], ['INP', 'inp_ms', ' ms', 'inp_status'], ['CLS', 'cls', '', 'cls_status']] as [$label, $metric, $suffix, $status])
                                                    <div class="rounded-md bg-slate-50 p-2">
                                                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                                        <dd class="mt-1 font-semibold tabular-nums">{{ data_get($result, 'field.'.$metric) !== null ? number_format(data_get($result, 'field.'.$metric), $metric === 'cls' ? 2 : 0).$suffix : '—' }}</dd>
                                                        @if (data_get($result, 'field.'.$status))
                                                            <dd @class([
                                                                'mt-1 text-xs font-medium',
                                                                'text-emerald-700' => data_get($result, 'field.'.$status) === 'good',
                                                                'text-amber-700' => data_get($result, 'field.'.$status) === 'needs_improvement',
                                                                'text-red-700' => data_get($result, 'field.'.$status) === 'poor',
                                                            ])>{{ str_replace('_', ' ', data_get($result, 'field.'.$status)) }}</dd>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @else
                                        <p class="mt-4 rounded-md bg-slate-50 p-3 text-xs text-slate-600">Not enough real-user traffic is available for this page yet.</p>
                                    @endif

                                    <div class="mt-4">
                                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lab measurements</p>
                                        <dl class="mt-2 grid grid-cols-3 gap-2 text-sm sm:grid-cols-5">
                                            @foreach ([['LCP', 'lcp_ms', ' ms'], ['CLS', 'cls', ''], ['TBT', 'tbt_ms', ' ms'], ['FCP', 'fcp_ms', ' ms'], ['Speed index', 'speed_index_ms', ' ms']] as [$label, $metric, $suffix])
                                                <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium tabular-nums">{{ data_get($result, 'lab.'.$metric) !== null ? number_format(data_get($result, 'lab.'.$metric), $metric === 'cls' ? 2 : 0).$suffix : '—' }}</dd></div>
                                            @endforeach
                                        </dl>
                                    </div>

                                    @if (filled($result['recommendations']))
                                        <div class="mt-4 border-t border-slate-100 pt-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Top improvements</p>
                                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                                @foreach ($result['recommendations'] as $recommendation)
                                                    <li>
                                                        <span class="font-medium text-slate-900">{{ $recommendation['title'] }}</span>
                                                        @if ($recommendation['savings_ms'] > 0)
                                                            · approximately {{ number_format($recommendation['savings_ms']) }} ms potential saving
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

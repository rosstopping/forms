        <section class="ui-panel ui-section" aria-labelledby="impact-results">
            <h2 id="impact-results" class="font-semibold text-slate-950">Search results</h2>
            <p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">First-party Search Console data, with a reporting delay. The baseline covers the 28 days before delivery; reviews use days 1–28 and 29–56 afterwards. The deployment day is excluded. Query filters omit anonymised searches, and missing data is not treated as zero. Seasonal demand, competitors, and Google updates can also affect results.</p>
            @if ($seoImpact->baseline)
                <p class="mt-3 text-slate-500 text-base sm:text-sm">{{ $seoImpact->live_at ? 'Baseline' : 'Provisional baseline · refreshed automatically after merge' }}: {{ $seoImpact->baseline['start'] }} – {{ $seoImpact->baseline['end'] }}@if ($seoImpact->observations) · Current window: {{ $seoImpact->observations['start'] }} – {{ $seoImpact->observations['end'] }}@endif</p>
                <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-950/10"><th class="py-2">Measure</th><th>Before</th><th>Latest window</th></tr></thead><tbody>
                    @foreach (['clicks' => 'Clicks', 'impressions' => 'Impressions', 'ctr' => 'CTR (%)', 'position' => 'Average position', 'reported_days' => 'Days with reported activity'] as $key => $label)
                        <tr class="border-b border-slate-100"><th class="py-3 font-medium">{{ $label }}</th><td class="tabular-nums">{{ isset($baseline[$key]) ? number_format($baseline[$key] * ($key === 'ctr' ? 100 : 1), in_array($key, ['ctr', 'position']) ? 2 : 0) : 'Unavailable' }}</td><td class="tabular-nums">{{ isset($current[$key]) ? number_format($current[$key] * ($key === 'ctr' ? 100 : 1), in_array($key, ['ctr', 'position']) ? 2 : 0) : 'Unavailable' }}</td></tr>
                    @endforeach
                </tbody></table></div>
            @else
                <p class="mt-4 text-slate-500 text-base sm:text-sm">Sitewell collects the baseline automatically, then compares results at four and eight weeks, allowing three days for Search Console reporting.</p>
            @endif
            @foreach ($seoImpact->reviews as $review)
                <article class="ui-well mt-4 p-4"><h3 class="text-sm font-semibold">Day {{ $review->checkpoint }} · {{ str($review->outcome)->replace('_', ' ')->ucfirst() }}</h3><p class="mt-1 text-slate-500 text-base sm:text-sm">{{ $review->period_start->toDateString() }} – {{ $review->period_end->toDateString() }}</p><p class="mt-2 text-slate-600 text-base sm:text-sm">{{ data_get($review->assessment, 'reason') }}</p>@if (data_get($review->assessment, 'percent') !== null)<p class="mt-2 font-medium text-base sm:text-sm">{{ data_get($review->assessment, 'percent') > 0 ? '+' : '' }}{{ data_get($review->assessment, 'percent') }}% {{ strtoupper($seoImpact->primary_metric) }}</p>@endif</article>
            @endforeach
            @if ($seoImpact->status === 'measuring')<p class="mt-4 text-slate-600 text-base sm:text-sm">Next checkpoint: day {{ $seoImpact->review_after_days }}. Target pages and search terms remain protected from overlapping content work.</p>@endif
        </section>

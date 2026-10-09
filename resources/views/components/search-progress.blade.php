@props(['report'])
@php($period = $report['period'])
<form method="GET" action="{{ url()->current() }}" class="mt-5 flex flex-wrap items-end gap-3">
    @if(request('weekly_report'))<input type="hidden" name="weekly_report" value="{{ request('weekly_report') }}">@endif
    <div><label for="report-period" class="ui-label">Period</label><select id="report-period" name="period" class="ui-input mt-1">
        @foreach(['7d' => 'Last 7 days', '28d' => 'Last 28 days', '3m' => 'Last 3 months', '6m' => 'Last 6 months', '12m' => 'Last 12 months', 'custom' => 'Custom dates'] as $key => $label)
            <option value="{{ $key }}" @selected($period->preset === $key)>{{ $label }}</option>
        @endforeach
    </select></div>
    <div><label for="report-comparison" class="ui-label">Compare with</label><select id="report-comparison" name="comparison" class="ui-input mt-1"><option value="previous" @selected($period->comparison === 'previous')>Previous period</option><option value="year" @selected($period->comparison === 'year')>Previous year</option></select></div>
    <details class="w-full" @if($period->preset === 'custom') open @endif><summary class="cursor-pointer text-sm text-slate-600">Custom date range</summary><div class="mt-2 flex flex-wrap gap-3">
        <div><label for="report-start" class="ui-label">Start</label><input id="report-start" name="start" type="date" value="{{ $period->start->toDateString() }}" class="ui-input mt-1"></div>
        <div><label for="report-end" class="ui-label">End</label><input id="report-end" name="end" type="date" max="{{ \App\Services\ReportingPeriod::cutoff()->toDateString() }}" value="{{ $period->end->toDateString() }}" class="ui-input mt-1"></div>
    </div></details>
    <button class="ui-button ui-button-secondary">Apply period</button>
</form>
@if($report['access_denied'])<p class="mt-4 text-sm text-amber-800">Search Console access needs attention. These are previously imported figures.</p>@endif
<div class="mt-5 space-y-5">
@foreach(['current' => [$period->start, $period->end], 'previous' => [$period->previousStart, $period->previousEnd]] as $key => $dates)
    @php($metrics = $report[$key])
    <div><h3 class="text-sm font-medium text-slate-700">{{ $key === 'current' ? 'Selected period' : 'Comparison period' }} · {{ $dates[0]->format('j M Y') }} – {{ $dates[1]->format('j M Y') }}</h3>
    @if($metrics['reported_days'])
        <dl class="mt-2 grid grid-cols-2 gap-4 @md:grid-cols-4">
            <div><dt class="text-sm text-slate-500">Clicks</dt><dd class="text-xl font-semibold tabular-nums">{{ number_format($metrics['clicks']) }}</dd></div>
            <div><dt class="text-sm text-slate-500">Impressions</dt><dd class="text-xl font-semibold tabular-nums">{{ number_format($metrics['impressions']) }}</dd></div>
            <div><dt class="text-sm text-slate-500">Click rate</dt><dd class="text-xl font-semibold tabular-nums">{{ $metrics['ctr'] === null ? '—' : number_format($metrics['ctr'] * 100, 1).'%' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Average position</dt><dd class="text-xl font-semibold tabular-nums">{{ $metrics['position'] === null ? '—' : number_format($metrics['position'], 1) }}</dd></div>
        </dl>
    @else<p class="mt-2 text-sm text-slate-500">No daily search performance imported for this period yet.</p>@endif
    @unless($metrics['complete'])<p class="mt-2 text-sm text-amber-800">Partial coverage · {{ $metrics['reported_days'] }} of {{ $metrics['days'] }} days have reported data. Missing days are not counted as zero.</p>@endunless
    </div>
@endforeach
</div>
@if($report['click_change'] !== null)<p class="mt-4 text-sm font-medium text-slate-700">Clicks {{ $report['click_change'] >= 0 ? '+' : '' }}{{ number_format($report['click_change'], 1) }}% across equal periods.</p>@endif
<p class="mt-4 text-sm text-slate-500">Search Console dates use Pacific time and allow at least three days for reporting. {{ $report['last_date'] ? 'Latest imported date: '.$report['last_date'].'.' : 'Daily imports are awaiting the scheduled sync.' }} Compare with the previous year to help assess seasonality; changes alone do not explain their cause. Longer comparisons may need history beyond Google’s available range.</p>

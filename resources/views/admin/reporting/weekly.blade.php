@extends('layouts.app')
@section('content')
<header><a class="text-teal-800 underline underline-offset-4" href="{{ route('admin.reporting.site', $website) }}">{{ $website->name }}</a><h1 class="mt-3 text-3xl font-semibold">Weekly SEO reports</h1></header>
@if($report)
<section class="ui-panel ui-section"><h2 class="text-xl font-semibold">{{ $report->period_start->format('j M') }} to {{ $report->period_end->format('j M Y') }}</h2>
<div class="mt-4 max-w-4xl whitespace-pre-line leading-7">{{ $report->overview }}</div>
@if($metrics = $report->snapshot['metric_cards'] ?? [])
    <dl class="my-5 grid gap-4 border-y border-slate-200 py-4 sm:grid-cols-3">@foreach($metrics as $metric)
        <div><dt class="text-sm text-slate-600">{{ $metric['label'] }}</dt><dd class="text-xl font-semibold tabular-nums">{{ is_numeric($metric['current'] ?? null) ? $metric['current'] : '—' }}</dd></div>
    @endforeach</dl>
@endif
@if($movements = data_get($report->snapshot, 'rankings.movements', []))
    <h3 class="mt-5 font-semibold">Keyword movements</h3>
    <ul class="mt-2 divide-y divide-slate-100">@foreach($movements as $movement)
        <li class="py-2">{{ $movement['term'] }} · {{ $movement['previous'] ?? 'Outside top 100' }} → {{ $movement['current'] ?? 'Outside top 100' }}</li>
    @endforeach</ul>
@endif
@foreach($report->snapshot['sections'] ?? [] as $section)
    <details class="mt-4 border-t border-slate-200 pt-3"><summary class="cursor-pointer font-semibold">{{ $section['title'] }}</summary><p class="mt-2 leading-7">{{ $section['summary'] }}</p></details>
@endforeach
@if($report->recommended_priority)<h3 class="mt-5 font-semibold">{{ $report->recommended_priority['title'] }}</h3><p>{{ $report->recommended_priority['reason'] ?? '' }}</p>@endif
</section>
<section><h2 class="text-lg font-semibold">Report history</h2><ul class="my-3 space-y-2">@foreach($history as $week)<li><a class="text-teal-800 underline" href="{{ route('admin.reporting.weekly', [$website, 'weekly_report' => $week->id]) }}">{{ $week->period_start->format('j M') }} to {{ $week->period_end->format('j M Y') }}</a></li>@endforeach</ul>{{ $history->links() }}</section>
@else<p class="ui-panel ui-section">No weekly reports have been saved for this website.</p>@endif
@endsection

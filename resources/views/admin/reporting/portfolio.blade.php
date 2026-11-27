@extends('layouts.app')
@section('content')
<header>
    <h1 class="text-3xl font-semibold tracking-tight text-slate-950">SEO overview</h1>
    <p class="mt-2 text-slate-600">{{ $rows->count() }} {{ Str::plural('website', $rows->count()) }} · Search clicks for {{ $cutoff->subDays(6)->format('j M') }} to {{ $cutoff->format('j M Y') }}</p>
</header>
@if($rows->isNotEmpty())
@include('admin.reporting.attention', ['portfolio' => true])
<section aria-labelledby="sites-title">
    <h2 id="sites-title" class="mb-3 text-xl font-semibold">Website reports</h2>
    <ul class="divide-y divide-slate-200 sm:hidden">
        @foreach($rows as $row)
            <li class="py-4">
                <a class="font-semibold text-teal-800 underline underline-offset-4" href="{{ route('admin.reporting.site', $row['site']) }}">{{ $row['site']->name }}</a>@unless($row['site']->is_active)<span> · Inactive</span>@endunless
                @if($row['report'])<a class="ml-2 text-sm text-teal-800 underline" href="{{ route('admin.reporting.weekly', $row['site']) }}">Report · {{ $row['report']->period_end->format('j M') }}</a>@endif
                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm tabular-nums">
                    <div><dt class="text-slate-600">Search clicks</dt><dd>{{ $row['clicks'] === null ? '—' : number_format($row['clicks']) }} @if($row['days'] > 0 && $row['days'] < 7)({{ $row['days'] }}/7 days)@endif</dd></div>
                    <div><dt class="text-slate-600">Top 10 keywords</dt><dd>{{ $row['ranks']?->top_ten ?? '—' }}</dd></div>
                    <div><dt class="text-slate-600">Indexed sample</dt><dd>{{ $row['index'] ? $row['index']->metrics['indexed'].' / '.$row['index']->metrics['checked'].' · '.$row['index']->period_end->format('j M') : '—' }}</dd></div>
                    <div><dt class="text-slate-600">GA4 sessions / users</dt><dd>{{ $row['analytics'] ? number_format($row['analytics']->metrics['sessions']).' / '.number_format($row['analytics']->metrics['active_users']) : '—' }}</dd></div>
                </dl>
                @if($row['analytics'])<p class="mt-2 text-sm text-slate-600">GA4 week ending {{ $row['analytics']->period_end->format('j M Y') }}</p>@endif
            </li>
        @endforeach
    </ul>
    <div class="hidden overflow-x-auto sm:block">
        <table class="w-full text-left text-sm tabular-nums">
            <thead class="whitespace-nowrap text-slate-600"><tr>
                @foreach(['Website', 'Search clicks', 'Top 10 keywords', 'Indexed sample', 'GA4 sessions', 'Active users', 'Week ending'] as $heading)
                    <th scope="col" class="border-b border-slate-300 px-3 py-3 font-medium first:pl-0">{{ $heading }}</th>
                @endforeach
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($rows as $row)
                    <tr>
                        <th scope="row" class="min-w-36 py-4 pr-3 font-semibold"><a class="text-teal-800 underline underline-offset-4" href="{{ route('admin.reporting.site', $row['site']) }}">{{ $row['site']->name }}</a>@unless($row['site']->is_active)<span class="block text-xs font-normal">Inactive</span>@endunless
                            @if($row['report'])<a href="{{ route('admin.reporting.weekly', $row['site']) }}" class="mt-1 block text-xs font-normal text-teal-800 underline">Report · {{ $row['report']->period_end->format('j M Y') }}</a>@endif
                        </th>
                        <td class="px-3">{{ $row['clicks'] === null ? '—' : number_format($row['clicks']) }}@if($row['days'] > 0 && $row['days'] < 7)<small class="block whitespace-nowrap text-slate-600">{{ $row['days'] }}/7 days</small>@endif</td>
                        <td class="px-3">{{ $row['ranks']?->top_ten ?? '—' }}</td>
                        <td class="px-3 whitespace-nowrap">{{ $row['index'] ? $row['index']->metrics['indexed'].' / '.$row['index']->metrics['checked'] : '—' }}@if($row['index'])<small class="block text-slate-600">{{ $row['index']->period_end->format('j M Y') }}</small>@endif</td>
                        <td class="px-3">{{ $row['analytics'] ? number_format($row['analytics']->metrics['sessions']) : '—' }}</td>
                        <td class="px-3">{{ $row['analytics'] ? number_format($row['analytics']->metrics['active_users']) : '—' }}</td>
                        <td class="px-3 whitespace-nowrap">{{ $row['analytics']?->period_end->format('j M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-slate-600">Top 10 counts tracked keywords checked in the past seven days. GA4 totals cover the week ending on the date shown. A dash means no figure is available.</p>
</section>
@else
<p class="ui-panel ui-section">Your account has no websites assigned.</p>
@endif
@endsection

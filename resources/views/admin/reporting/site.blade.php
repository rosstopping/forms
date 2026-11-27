@extends('layouts.app')
@section('content')
<header class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="text-3xl font-semibold tracking-tight">{{ $website->name }}</h1>
    <nav aria-label="Website reports" class="flex flex-wrap gap-4 font-medium text-teal-800">
        <a href="{{ route('admin.reporting.weekly', $website) }}" class="underline underline-offset-4">Weekly reports</a>
        <a href="{{ route('admin.search-overview', $website) }}" class="underline underline-offset-4">Search performance</a>
    </nav>
</header>
<section aria-labelledby="traffic-title">
    <h2 id="traffic-title" class="text-xl font-semibold">Traffic and users</h2>
    <dl class="mt-4 grid gap-4 border-y border-slate-200 py-5 sm:grid-cols-3">
        <div><dt>Google Search clicks</dt><dd class="my-1 text-3xl font-semibold tabular-nums">{{ $row['clicks'] === null ? '—' : number_format($row['clicks']) }}</dd><dd class="text-sm text-slate-600">{{ $cutoff->subDays(6)->format('j M') }} to {{ $cutoff->format('j M Y') }} · {{ $row['days'] }}/7 days recorded</dd></div>
        @foreach(['sessions' => 'GA4 sessions', 'active_users' => 'GA4 active users'] as $key => $label)
            <div><dt>{{ $label }}</dt><dd class="my-1 text-3xl font-semibold tabular-nums">{{ $row['analytics'] ? number_format($row['analytics']->metrics[$key]) : '—' }}</dd><dd class="text-sm text-slate-600">{{ $row['analytics'] ? $row['analytics']->period_start->format('j M').' to '.$row['analytics']->period_end->format('j M Y') : (config('reporting.analytics_properties.'.$website->id) ? 'Awaiting GA4 data' : 'No GA4 property linked') }}</dd></div>
        @endforeach
    </dl>
</section>
@include('admin.reporting.attention')
<section id="indexing" class="space-y-3">
    <h2 class="text-xl font-semibold">Google indexing</h2>
    @if($index = $row['index'])
        <p>{{ $index->metrics['indexed'] }} of {{ $index->metrics['checked'] }} sampled URLs indexed · Checked {{ $index->period_end->format('j M Y') }}</p>
        <ul id="index-verdicts" class="divide-y divide-slate-200">
            @foreach($index->metrics['urls'] as $check)
                <li class="flex flex-wrap justify-between gap-2 py-2"><span class="min-w-0 break-all">{{ $check['url'] }}</span><span class="font-medium">{{ $check['coverage'] ?? $check['verdict'] }}</span></li>
            @endforeach
        </ul>
    @else
        <p class="text-slate-600">No Google URL inspections recorded.</p>
    @endif
    @if($website->searchConsoleConnection?->property_url)
        <a class="text-teal-800 underline" href="https://search.google.com/search-console/index?resource_id={{ rawurlencode($website->searchConsoleConnection->property_url) }}">Full page indexing report in Search Console</a>
    @endif
    @if($website->latestHealthReport)
        <a class="block text-teal-800 underline" href="{{ route('admin.website-health-reports.show', [$website, $website->latestHealthReport]) }}">Latest website crawl</a>
    @endif
    @if(! $website->searchConsoleConnection?->property_url || $website->searchConsoleConnection->access_denied_at || (config('reporting.analytics_properties.'.$website->id) && ! $row['analytics']))
        <a id="google-connection" class="block text-teal-800 underline" href="{{ route('admin.search-console.connect', $website) }}">Connect Google reporting</a>
    @endif
</section>
<section id="rankings">
    <h2 class="text-xl font-semibold">Tracked keyword rankings</h2>
    @if($rankings->isEmpty())
        <p class="mt-2 text-slate-600">No active keywords are being tracked.</p>
    @else
        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr>@foreach(['Keyword', 'Position', 'Previous', 'Last successful check'] as $heading)<th scope="col" class="whitespace-nowrap border-b border-slate-300 py-2 pr-4 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-200">@foreach($rankings as $rank)
                <tr><th scope="row" class="py-3 pr-4 font-medium">{{ $rank['target']->term }}</th>
                    @foreach(['latest', 'previous'] as $observation)<td class="pr-4">{{ $rank[$observation] ? ($rank[$observation]->position ?? 'Outside top 100') : '—' }}</td>@endforeach
                    <td>{{ $rank['latest']?->observed_at->format('j M Y') ?? 'Awaiting check' }} @if($rank['latest_failed'])<span class="block text-amber-800">Latest check failed</span>@endif</td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>
<section id="content"><h2 class="text-xl font-semibold">Content ideas</h2><ul class="mt-2 divide-y divide-slate-200">
    @forelse($ideas as $idea)<li class="py-2">{{ $idea->title }}</li>@empty<li class="text-slate-600">No open content suggestions.</li>@endforelse
</ul></section>
@endsection

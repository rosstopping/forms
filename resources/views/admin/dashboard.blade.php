@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="relative overflow-hidden rounded-2xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-950/10 sm:p-8">
        <div class="absolute -top-24 right-0 size-64 rounded-full bg-teal-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-mono text-xs font-medium uppercase tracking-widest text-teal-300">Website monitoring</p>
                <h1 class="mt-2 text-white">Site health overview</h1>
                <p class="mt-2 max-w-2xl text-base text-slate-300 sm:text-sm">Review audit findings across every website and open a site to investigate its search performance, content, forms, and submissions.</p>
            </div>
            <a href="{{ route('admin.websites.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-teal-400 px-3 py-2 text-sm font-medium text-slate-950 hover:bg-teal-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-400">View websites</a>
        </div>
    </header>

    <div class="@container">
        <dl class="grid grid-cols-2 gap-3 @2xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="truncate text-sm font-medium text-slate-500">Websites</dt><dd class="mt-3 text-3xl font-semibold tabular-nums text-slate-950">{{ $websites->count() }}</dd><div class="mt-4 h-1 w-10 rounded-full bg-slate-300"></div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="truncate text-sm font-medium text-slate-500">Healthy</dt><dd class="mt-3 text-3xl font-semibold tabular-nums text-emerald-700">{{ $healthyCount }}</dd><div class="mt-4 h-1 w-10 rounded-full bg-emerald-400"></div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="truncate text-sm font-medium text-slate-500">Needs attention</dt><dd class="mt-3 text-3xl font-semibold tabular-nums text-amber-700">{{ $needsAttentionCount }}</dd><div class="mt-4 h-1 w-10 rounded-full bg-amber-400"></div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="truncate text-sm font-medium text-slate-500">Not yet audited</dt><dd class="mt-3 text-3xl font-semibold tabular-nums text-slate-700">{{ $notAuditedCount }}</dd><div class="mt-4 h-1 w-10 rounded-full bg-slate-300"></div></div>
        </dl>
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white" aria-labelledby="websites-heading">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-5">
            <div><h2 id="websites-heading" class="font-semibold text-slate-950">Websites</h2><p class="text-sm text-slate-600">Latest audit position for each property.</p></div>
            @if (Auth::user()?->isAdmin())<a href="{{ route('admin.websites.create') }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Add website</a>@endif
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($websites as $website)
                @php($report = $website->latestHealthReport)
                <a href="{{ route('admin.websites.show', $website) }}" class="grid gap-3 p-5 hover:bg-teal-50/40 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate font-medium text-slate-950">{{ $website->name }}</h3>
                            <span @class(['rounded-full px-2 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-700' => $report?->overall_status === 'healthy', 'bg-amber-50 text-amber-700' => $report?->overall_status === 'needs_attention', 'bg-red-50 text-red-700' => $report?->overall_status === 'critical', 'bg-slate-100 text-slate-600' => ! $report || ! in_array($report->overall_status, ['healthy', 'needs_attention', 'critical'], true)])>{{ $report ? ucfirst(str_replace('_', ' ', $report->overall_status ?: $report->status)) : 'Not audited' }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $website->domains->firstWhere('is_primary', true)?->domain ?: $website->domains->first()?->domain ?: 'No domain recorded' }}</p>
                    </div>
                    <div class="flex gap-5 text-sm text-slate-500 sm:justify-end">
                        @if ($report)<span><strong class="font-medium tabular-nums text-red-700">{{ $report->failed_checks }}</strong> failed</span><span><strong class="font-medium tabular-nums text-amber-700">{{ $report->warning_checks }}</strong> warnings</span><span>{{ $report->created_at->diffForHumans() }}</span>@else<span>Run the first audit</span>@endif
                    </div>
                </a>
            @empty
                <p class="p-6 text-center text-sm text-slate-500">No websites are available yet.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="recent-audits-heading">
        <h2 id="recent-audits-heading" class="font-semibold text-slate-950">Recent audit activity</h2>
        <div class="mt-3 divide-y divide-slate-100">
            @forelse ($recentReports as $report)
                <a href="{{ route('admin.website-health-reports.show', [$report->website, $report]) }}" class="flex flex-col gap-1 py-3 hover:text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                    <span class="font-medium">{{ $report->website->name }}</span>
                    <span class="text-sm text-slate-500">{{ ucfirst(str_replace('_', ' ', $report->overall_status ?: $report->status)) }} · {{ $report->failed_checks }} failed · {{ $report->warning_checks }} warnings · {{ $report->created_at->diffForHumans() }}</span>
                </a>
            @empty
                <p class="py-3 text-sm text-slate-500">Audit activity will appear here after the first report runs.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection

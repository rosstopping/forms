@extends('layouts.app')

@section('content')
@if (! $website)
    <section class="mx-auto max-w-2xl rounded-xl border border-slate-200 bg-white p-8 text-center">
        <h1 class="text-2xl font-semibold text-slate-950">Welcome to Sitewell</h1>
        <p class="mt-2 text-base text-slate-600 sm:text-sm">Add a website to start monitoring its health and planning improvements.</p>
        @if (Auth::user()?->isAdmin())
            <a href="{{ route('admin.websites.create') }}" class="mt-6 inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Add a website</a>
        @endif
    </section>
@else
    @php
        $status = $report?->overall_status ?: $report?->status;
        $statusLabel = $report ? ucfirst(str_replace('_', ' ', $status)) : 'Not checked yet';
        $primaryDomain = $website->domains->firstWhere('is_primary', true)?->domain ?: $website->domains->first()?->domain;
        $newIssues = (int) data_get($report?->metrics, 'changes.new_issues', 0);
        $resolvedIssues = (int) data_get($report?->metrics, 'changes.resolved_issues', 0);
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="font-mono text-xs text-teal-700">Website overview</p>
                <h1 class="mt-1 truncate text-2xl font-semibold text-slate-950 sm:text-3xl">{{ $website->name }}</h1>
                <p class="mt-2 text-base text-slate-600 sm:text-sm">Your latest website health, content work, and connected services in one place.</p>
            </div>
            @if ($primaryDomain)
                <a href="{{ str_starts_with($primaryDomain, 'http') ? $primaryDomain : 'https://'.$primaryDomain }}" target="_blank" rel="noreferrer" class="inline-flex shrink-0 items-center gap-2 text-sm font-medium text-slate-600 hover:text-teal-700">Visit website <span aria-hidden="true">↗</span></a>
            @endif
        </header>

        @if ($isTrialActive)
            <section class="flex flex-col gap-3 rounded-xl border border-teal-200 bg-teal-50 p-4 sm:flex-row sm:items-center sm:justify-between" aria-label="Trial status">
                <div>
                    <p class="font-semibold text-teal-950">Your Essential trial is active</p>
                    <p class="mt-1 text-sm text-teal-800">You have {{ max(1, (int) now()->diffInDays(Auth::user()->onboarding_trial_ends_at, false)) }} days remaining. Weekly health reports are included during your trial.</p>
                </div>
                <a href="{{ route('admin.billing.index') }}" class="shrink-0 text-sm font-semibold text-teal-800 hover:text-teal-950">View trial details</a>
            </section>
        @endif

        <section class="@container overflow-hidden rounded-xl border border-slate-200 bg-white" aria-labelledby="website-health-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="website-health-heading" class="text-xl font-semibold text-slate-950">Website health</h2>
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $status === 'healthy',
                            'bg-amber-50 text-amber-700' => $status === 'needs_attention',
                            'bg-red-50 text-red-700' => $status === 'critical',
                            'bg-slate-100 text-slate-600' => ! in_array($status, ['healthy', 'needs_attention', 'critical'], true),
                        ])>{{ $statusLabel }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        @if ($report?->completed_at)
                            Latest report completed {{ $report->completed_at->diffForHumans() }}.
                        @elseif ($report)
                            Your latest report is {{ strtolower($statusLabel) }}.
                        @else
                            Run your first report to establish a health baseline.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($report)
                        <a href="{{ route('admin.website-health-reports.show', [$website, $report]) }}" class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">View health report</a>
                    @elseif ($canManageWebsite)
                        <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">@csrf<button type="submit" class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Run health report</button></form>
                    @endif
                    @if ($report && $canManageWebsite)
                        <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">@csrf<button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Run again</button></form>
                    @endif
                </div>
            </div>

            @if ($report)
                <dl class="grid grid-cols-2 border-b border-slate-200 @2xl:grid-cols-4">
                    <div class="border-b border-r border-slate-200 p-4 @2xl:border-b-0"><dt class="truncate text-sm text-slate-500">Passed</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ $report->passed_checks }}</dd></div>
                    <div class="border-b border-slate-200 p-4 @2xl:border-b-0 @2xl:border-r"><dt class="truncate text-sm text-slate-500">Warnings</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-amber-700">{{ $report->warning_checks }}</dd></div>
                    <div class="border-r border-slate-200 p-4"><dt class="truncate text-sm text-slate-500">Failed</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-red-700">{{ $report->failed_checks }}</dd></div>
                    <div class="p-4"><dt class="truncate text-sm text-slate-500">Since last report</dt><dd class="mt-1 text-sm font-semibold text-slate-900"><span class="text-emerald-700">{{ $resolvedIssues }} resolved</span> · <span class="text-red-700">{{ $newIssues }} new</span></dd></div>
                </dl>
                <div class="p-5 sm:p-6">
                    <h3 class="font-semibold text-slate-950">What needs attention</h3>
                    <div class="mt-3 divide-y divide-slate-100">
                        @forelse ($topFindings as $finding)
                            <div class="flex gap-3 py-3 first:pt-0 last:pb-0">
                                <span @class(['mt-1 size-2 shrink-0 rounded-full', 'bg-red-500' => $finding['status'] === 'failed', 'bg-amber-400' => $finding['status'] !== 'failed'])></span>
                                <div class="min-w-0"><p class="font-medium text-slate-900">{{ $finding['label'] }}</p><p class="mt-0.5 text-sm text-slate-600">{{ $finding['message'] }}</p>@if ($finding['url'])<p class="mt-1 truncate text-xs text-slate-400">{{ $finding['url'] }}</p>@endif</div>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-slate-600">No outstanding findings were recorded in the latest report.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="content-heading">
                <div class="flex items-start justify-between gap-4"><div><h2 id="content-heading" class="font-semibold text-slate-950">Website content</h2><p class="mt-1 text-sm text-slate-600">Ideas and updates waiting to be prepared.</p></div><a href="{{ route('admin.websites.section', [$website, 'content']) }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:text-teal-900">View content</a></div>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Pending requests</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $website->pending_content_requests_count }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Next preparation</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $nextContentRun ? $nextContentRun['next_run_at']->format('D j M') : 'Not scheduled' }}</dd></div>
                </dl>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($website->contentRequests->take(3) as $contentRequest)
                        <div class="py-3 first:pt-0 last:pb-0"><p class="line-clamp-2 text-sm font-medium text-slate-800">{{ $contentRequest->instructions }}</p><p class="mt-1 text-xs text-slate-500">{{ $contentRequest->picked_up_at ? 'Preparation started' : 'Waiting to be prepared' }} · {{ $contentRequest->created_at->diffForHumans() }}</p></div>
                    @empty
                        <p class="text-sm text-slate-600">No content requests yet. Add an idea when you are ready to improve or expand a page.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="schedule-heading">
                <h2 id="schedule-heading" class="font-semibold text-slate-950">Next health check</h2>
                @if ($nextHealthRun)
                    <time datetime="{{ $nextHealthRun['next_run_at']->toIso8601String() }}" class="mt-4 block text-2xl font-semibold tabular-nums text-slate-950">{{ $nextHealthRun['next_run_at']->format('D j M, H:i') }}</time>
                    <p class="mt-1 text-sm text-slate-600">{{ $nextHealthRun['next_run_at']->diffForHumans() }} · {{ config('app.timezone') }}</p>
                    <p class="mt-5 text-sm text-slate-600">Weekly reports help you spot regressions and new issues before they become bigger problems.</p>
                @else
                    <p class="mt-4 text-sm text-slate-600">Automatic health reports are not currently scheduled for this website.</p>
                @endif
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="@container rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="search-heading">
                <div class="flex items-start justify-between gap-4"><div><h2 id="search-heading" class="font-semibold text-slate-950">Google Search Console</h2><p class="mt-1 text-sm text-slate-600">Organic search performance from your connected property.</p></div><a href="{{ route('admin.websites.section', [$website, 'search']) }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:text-teal-900">{{ $website->searchConsoleConnection ? 'View search' : 'Connect' }}</a></div>
                @if ($website->searchConsoleConnection && $latestSearchMetric)
                    <p class="mt-5 text-xs font-medium text-slate-500">{{ $latestSearchMetric->month->format('F Y') }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-5 gap-y-4 @md:grid-cols-4">
                        <div><dt class="text-sm text-slate-500">Clicks</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($latestSearchMetric->clicks) }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Impressions</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($latestSearchMetric->impressions) }}</dd></div>
                        <div><dt class="text-sm text-slate-500">Click rate</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($latestSearchMetric->ctr * 100, 1) }}%</dd></div>
                        <div><dt class="text-sm text-slate-500">Position</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($latestSearchMetric->position, 1) }}</dd></div>
                    </dl>
                @elseif ($website->searchConsoleConnection)
                    <p class="mt-5 text-sm text-slate-600">Connected. Search performance will appear after the first metrics import.</p>
                @else
                    <p class="mt-5 text-sm text-slate-600">Connect Search Console to see clicks, visibility, and average position alongside website health.</p>
                @endif
            </section>

            @if (config('forms.pixel_ui_enabled') && $website->pixel_enabled)
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="pixel-heading">
                    <div class="flex items-start justify-between gap-4"><div><h2 id="pixel-heading" class="font-semibold text-slate-950">Sitewell Pixel</h2><p class="mt-1 text-sm text-slate-600">Connected delivery and website activity.</p></div><a href="{{ route('admin.websites.section', [$website, 'pixel']) }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:text-teal-900">View Pixel</a></div>
                    <dl class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Live changes</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $website->live_pixel_changes_count }}</dd></div><div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Last seen</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $website->pixel_last_seen_at?->diffForHumans() ?: 'Waiting for activity' }}</dd></div></dl>
                </section>
            @endif

            @if ($website->forms_count > 0)
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="forms-heading">
                    <div class="flex items-start justify-between gap-4"><div><h2 id="forms-heading" class="font-semibold text-slate-950">Form activity</h2><p class="mt-1 text-sm text-slate-600">An optional view of responses received through Sitewell forms.</p></div><a href="{{ route('admin.websites.section', [$website, 'forms']) }}" class="shrink-0 text-sm font-semibold text-teal-700 hover:text-teal-900">View forms</a></div>
                    <dl class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Connected forms</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $website->forms_count }}</dd></div><div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-500">Submissions</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $website->submissions_count }}</dd></div></dl>
                </section>
            @endif
        </div>
    </div>
@endif
@endsection

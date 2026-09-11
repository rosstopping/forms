@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <header>
            <p class="font-mono text-xs text-teal-700">All websites</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 sm:text-3xl">Admin overview</h1>
            <p class="mt-2 text-sm text-slate-600">Upcoming automation and actions to review across every website.</p>
        </header>

        <dl class="grid gap-4 sm:grid-cols-3">
            @foreach (['Websites' => $websites->count(), 'Awaiting review' => $approvalCount, 'Requests awaiting preparation' => $websites->sum('pending_content_requests_count')] as $label => $count)
                <div class="rounded-xl border border-slate-200 bg-white p-5"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 text-3xl font-semibold tabular-nums text-slate-950">@if ($label === 'Requests awaiting preparation')<a href="#content-queue" class="text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900" aria-label="View {{ $count }} requests awaiting preparation">{{ number_format($count) }}</a>@else{{ number_format($count) }}@endif</dd></div>
            @endforeach
        </dl>

        <section id="content-queue" class="scroll-mt-20 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="content-queue-heading">
            <h2 id="content-queue-heading" class="text-xl font-semibold text-slate-950">Content queue</h2>
            <p class="mt-1 text-sm text-slate-600">Requests awaiting preparation across websites. Sites needing attention appear first. Each run selects eligible work; it may not clear the whole queue.</p>
            <div class="mt-5 divide-y divide-slate-100">
                @forelse ($contentQueue as $item)
                    <div class="grid gap-3 py-4 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto] sm:items-center">
                        <div class="min-w-0">
                            <a href="{{ \App\Support\WebsiteNavigation::routeFor($item['website'], 'content') }}" class="font-semibold text-slate-950 hover:text-teal-700">{{ $item['website']->name }}</a>
                            <p class="mt-1 text-sm text-slate-500">{{ number_format($item['count']) }} {{ Str::plural('request', $item['count']) }} waiting</p>
                        </div>
                        <div>
                            <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-amber-50 text-amber-800' => $item['state'] === 'Needs setup', 'bg-slate-100 text-slate-700' => $item['state'] === 'Paused', 'bg-teal-50 text-teal-800' => $item['state'] === 'Scheduled'])>{{ $item['state'] }}</span>
                            @if ($item['next_run_at'])
                                <p class="mt-2 text-sm font-medium text-slate-900"><time datetime="{{ $item['next_run_at']->toIso8601String() }}">{{ $item['next_run_at']->format('D j M, H:i') }}</time> · {{ config('app.timezone') }}</p>
                            @endif
                            <p class="mt-1 text-sm text-slate-600">{{ $item['reason'] }}</p>
                        </div>
                        <a href="{{ $item['url'] }}" class="inline-flex items-center py-2 text-sm font-semibold text-teal-700 hover:text-teal-900">{{ $item['action'] }} →</a>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No content requests awaiting preparation.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="approvals-heading">
            <h2 id="approvals-heading" class="text-xl font-semibold text-slate-950">Actions to review</h2>
            <p class="mt-1 text-sm text-slate-600">Open the website work to inspect changes and approve them.</p>
            <div class="mt-5 space-y-6">
                <div>
                    <h3 class="font-semibold text-slate-900">Draft changes · {{ $optimisations->total() }}</h3>
                    <div class="mt-2 divide-y divide-slate-100">
                        @forelse ($optimisations as $optimisation)
                            <a href="{{ $optimisation->page ? route('admin.website-health-report-pages.show', [$optimisation->website, $optimisation->page->website_health_report_id, $optimisation->page]) : route('admin.websites.section', [$optimisation->website, 'content']) }}" class="flex flex-col gap-1 py-3 text-sm hover:text-teal-700 sm:flex-row sm:items-center sm:justify-between">
                                <span class="min-w-0"><span class="font-medium">{{ $optimisation->website->name }}</span><span class="mt-1 block break-all text-slate-500">{{ $optimisation->url }}</span></span>
                                <span class="shrink-0 font-semibold text-teal-700">Review change →</span>
                            </a>
                        @empty
                            <p class="py-3 text-sm text-slate-500">No draft changes awaiting review.</p>
                        @endforelse
                    </div>
                    {{ $optimisations->withQueryString()->links() }}
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900">Content pull requests · {{ $contentReviews->total() }}</h3>
                    @forelse ($contentReviews as $generation)
                        <a href="{{ route('admin.websites.section', [$generation->plan->website, 'content']) }}" class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-3 text-sm"><span>{{ $generation->plan->website->name }} · #{{ $generation->pull_request_number }}</span><span class="font-semibold text-teal-700">Review content →</span></a>
                    @empty
                        <p class="py-3 text-sm text-slate-500">No content pull requests awaiting review.</p>
                    @endforelse
                    {{ $contentReviews->withQueryString()->links() }}
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900">Website fixes · {{ $remediationReviews->total() }}</h3>
                    @forelse ($remediationReviews as $run)
                        <a href="{{ route('admin.website-health-reports.show', [$run->report->website, $run->report]) }}" class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-3 text-sm"><span>{{ $run->report->website->name }} · #{{ $run->pull_request_number }}</span><span class="font-semibold text-teal-700">Review fixes →</span></a>
                    @empty
                        <p class="py-3 text-sm text-slate-500">No website fixes awaiting review.</p>
                    @endforelse
                    {{ $remediationReviews->withQueryString()->links() }}
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="schedule-heading">
            <h2 id="schedule-heading" class="text-xl font-semibold text-slate-950">Upcoming schedule</h2>
            <p class="mt-1 text-sm text-slate-600">Next health checks and content preparation runs. Times shown in {{ config('app.timezone') }}.</p>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($automationSchedule as $item)
                    <div class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><a href="{{ route('admin.websites.section', [$item['website'], $item['type'] === 'Health report' ? 'health' : 'content']) }}" class="font-medium text-slate-950 hover:text-teal-700">{{ $item['website']->name }}</a><p class="mt-1 text-sm text-slate-600">{{ $item['type'] }}</p></div>
                        <div class="text-sm sm:text-right"><time datetime="{{ $item['next_run_at']->toIso8601String() }}" class="font-semibold tabular-nums text-slate-900">{{ $item['next_run_at']->format('D j M, H:i') }}</time><p class="mt-1 text-slate-500">{{ $item['next_run_at']->diffForHumans() }}</p></div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">No upcoming automation is scheduled.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

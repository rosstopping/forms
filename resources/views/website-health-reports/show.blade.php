@extends('layouts.app')

@section('content')
@php
    $siteIssues = collect($report->checks)->whereIn('status', ['warning', 'failed']);
    $pagesWithIssues = $report->pages->filter(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->isNotEmpty());
    $issueCount = $siteIssues->count() + $pagesWithIssues->sum(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->count());
@endphp

<main class="mx-auto max-w-5xl space-y-6">
    <header class="rounded-xl bg-slate-900 p-6 text-white shadow-sm sm:p-8">
        <p class="text-sm font-medium text-slate-300">Weekly website review</p>
        <h1 class="mt-2 text-3xl font-semibold">Your website health report</h1>
        <p class="mt-2 text-lg text-slate-200">{{ $report->website->name }}</p>
        <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-300">We checked the website for availability, security, search visibility, content quality and accessibility. This report explains the findings that are worth acting on.</p>
        <p class="mt-4 text-xs text-slate-400">Report created {{ $report->created_at->format('j F Y \a\t g:ia') }}</p>
    </header>

    @if ($report->status === 'failed')
        <section class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-900">
            <h2 class="font-semibold">We could not complete this report</h2>
            <p class="mt-2 text-sm">{{ $report->error ?: 'The website could not be fully checked. Please contact the team for help.' }}</p>
        </section>
    @elseif ($report->status !== 'completed')
        <section class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-blue-900">
            <h2 class="font-semibold">The report is still being prepared</h2>
            <p class="mt-2 text-sm">Please try this link again shortly.</p>
        </section>
    @else
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-5 shadow-sm sm:col-span-2">
                <p class="text-sm font-medium text-slate-500">At a glance</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $issueCount === 0 ? 'Everything checked looks healthy' : $issueCount.' '.Str::plural('finding', $issueCount).' worth reviewing' }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ data_get($report->metrics, 'pages_analyzed', 0) }} {{ Str::plural('page', data_get($report->metrics, 'pages_analyzed', 0)) }} analysed. A failed check should be prioritised; a warning is an opportunity to improve rather than proof that something is broken.</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-sm font-medium text-emerald-800">Checks passed</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-900">{{ $report->passed_checks }}</p>
                <p class="mt-2 text-sm text-emerald-800">No action is needed for these checks.</p>
            </div>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-slate-900">What needs attention</h2>
            <p class="mt-2 text-sm text-slate-600">The most useful findings are listed below, with the affected page where applicable.</p>

            @if ($issueCount === 0)
                <div class="mt-5 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">No warnings or failed checks were found in this review.</div>
            @else
                <div class="mt-5 space-y-4">
                    @foreach ($siteIssues->sortBy(fn ($check) => $check['status'] === 'failed' ? 0 : 1) as $check)
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-100 text-red-800' => $check['status'] === 'failed', 'bg-amber-100 text-amber-800' => $check['status'] === 'warning'])>{{ $check['status'] === 'failed' ? 'Needs attention' : 'Worth improving' }}</span>
                                <span class="text-xs capitalize text-slate-500">{{ str_replace('_', ' ', $check['category']) }}</span>
                            </div>
                            <h3 class="mt-3 font-semibold text-slate-900">{{ $check['label'] }}</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ $check['message'] }}</p>
                        </article>
                    @endforeach

                    @foreach ($pagesWithIssues as $page)
                        @foreach (collect($page->checks)->whereIn('status', ['warning', 'failed'])->sortBy(fn ($check) => $check['status'] === 'failed' ? 0 : 1) as $check)
                            <article class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-100 text-red-800' => $check['status'] === 'failed', 'bg-amber-100 text-amber-800' => $check['status'] === 'warning'])>{{ $check['status'] === 'failed' ? 'Needs attention' : 'Worth improving' }}</span>
                                    <a class="break-all text-xs font-medium text-blue-700 hover:underline" href="{{ $page->url }}" target="_blank" rel="noreferrer">{{ parse_url($page->url, PHP_URL_PATH) ?: '/' }}</a>
                                </div>
                                <h3 class="mt-3 font-semibold text-slate-900">{{ $check['label'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $check['message'] }}</p>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </section>

        @if (data_get($report->metrics, 'search_console'))
            <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold text-slate-900">How people found the website</h2>
                <p class="mt-2 text-sm text-slate-600">Google Search Console activity for the reporting period. These figures show visibility in Google search, not all website visits.</p>
                <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Search clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.clicks', 0)) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Times shown</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.impressions', 0)) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Click-through rate</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.ctr', 0) * 100, 1) }}%</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.position', 0), 1) }}</dd></div>
                </dl>
            </section>
        @endif

        @if (data_get($report->metrics, 'forms_count', 0) > 0)
            <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold text-slate-900">Form activity</h2>
                <p class="mt-2 text-sm text-slate-600">In the last seven days the website received {{ data_get($report->metrics, 'legitimate_submissions', 0) }} legitimate {{ Str::plural('submission', data_get($report->metrics, 'legitimate_submissions', 0)) }} and filtered {{ data_get($report->metrics, 'spam_submissions', 0) }} as spam.</p>
            </section>
        @endif
    @endif
</main>
@endsection

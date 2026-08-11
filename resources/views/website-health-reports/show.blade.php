@extends('layouts.app')

@section('content')
@php
    $siteIssues = collect($report->checks)->whereIn('status', ['warning', 'failed']);
    $pagesWithIssues = $report->pages->filter(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->isNotEmpty());
    $issueCount = $siteIssues->count() + $pagesWithIssues->sum(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->count());
    $pageFindingMessage = function ($page, array $check): string {
        if ($check['status'] === 'warning' && $check['key'] === 'page_title' && Str::length((string) $page->title) > 65) {
            return 'The title is '.Str::length($page->title).' characters long. Aim for 65 or fewer so it is less likely to be truncated in search results. Current title: '.$page->title;
        }

        if ($check['status'] === 'warning' && $check['key'] === 'meta_description' && Str::length((string) $page->meta_description) > 170) {
            return 'The meta description is '.Str::length($page->meta_description).' characters long. Aim for 170 or fewer so it is less likely to be truncated in search results.';
        }

        return $check['message'];
    };
@endphp

<main class="mx-auto max-w-5xl space-y-6">
    <header class="rounded-xl bg-slate-900 p-6 text-white shadow-sm sm:p-8">
        <p class="text-sm font-medium text-slate-300">Weekly website review</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Your website health report</h1>
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

        @if (data_get($report->metrics, 'search_console'))
            <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold text-slate-900">How people found the website</h2>
                <p class="mt-2 text-sm text-slate-600">Google Search Console activity for {{ \Illuminate\Support\Carbon::parse(data_get($report->metrics, 'search_console.period.start'))->format('j M') }}–{{ \Illuminate\Support\Carbon::parse(data_get($report->metrics, 'search_console.period.end'))->format('j M Y') }}. These figures cover Google search visibility, not all website visits.</p>
                <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Search clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.clicks', 0)) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Times shown</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.impressions', 0)) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Click-through rate</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.ctr', 0) * 100, 1) }}%</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format(data_get($report->metrics, 'search_console.totals.position', 0), 1) }}</dd></div>
                </dl>

                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">Top search terms</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">What people searched for before seeing or visiting the website.</p>
                        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-3 py-2 font-medium">Search term</th><th class="px-3 py-2 text-right font-medium">Clicks</th><th class="px-3 py-2 text-right font-medium">Shown</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse (data_get($report->metrics, 'search_console.queries', []) as $query)
                                        <tr><td class="max-w-64 px-3 py-2.5 font-medium text-slate-800">{{ $query['query'] }}</td><td class="px-3 py-2.5 text-right text-slate-600">{{ number_format($query['clicks']) }}</td><td class="px-3 py-2.5 text-right text-slate-600">{{ number_format($query['impressions']) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No search terms were reported for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-slate-900">Top landing pages</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Pages people reached from Google search.</p>
                        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-3 py-2 font-medium">Landing page</th><th class="px-3 py-2 text-right font-medium">Clicks</th><th class="px-3 py-2 text-right font-medium">Shown</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse (data_get($report->metrics, 'search_console.pages', []) as $page)
                                        <tr><td class="max-w-64 px-3 py-2.5"><a class="block truncate font-medium text-blue-700 hover:underline" href="{{ $page['page'] }}" target="_blank" rel="noreferrer" title="{{ $page['page'] }}">{{ parse_url($page['page'], PHP_URL_PATH) ?: '/' }}</a></td><td class="px-3 py-2.5 text-right text-slate-600">{{ number_format($page['clicks']) }}</td><td class="px-3 py-2.5 text-right text-slate-600">{{ number_format($page['impressions']) }}</td></tr>
                                    @empty
                                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No landing pages were reported for this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if (data_get($report->metrics, 'content_updates'))
            <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold text-slate-900">Content changes this week</h2>
                <p class="mt-2 text-sm text-slate-600">These content updates were reviewed and merged into the website’s repository during this reporting period.</p>
                <div class="mt-5 space-y-4">
                    @foreach (data_get($report->metrics, 'content_updates', []) as $update)
                        <article class="rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-slate-900"><a class="hover:text-blue-700 hover:underline" href="{{ $update['url'] }}" target="_blank" rel="noreferrer">{{ $update['title'] }}</a></h3>
                                    <p class="mt-1 text-xs text-slate-500">Merged {{ \Illuminate\Support\Carbon::parse($update['merged_at'])->format('j F Y') }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ number_format($update['changed_files']) }} {{ Str::plural('file', $update['changed_files']) }}</span>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800">+{{ number_format($update['additions']) }}</span>
                                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-red-800">−{{ number_format($update['deletions']) }}</span>
                                </div>
                            </div>
                            @if ($update['summary'])
                                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $update['summary'] }}</p>
                            @endif
                            @if ($update['files'])
                                <div class="mt-4 border-t border-slate-100 pt-3">
                                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Files changed</p>
                                    <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                        @foreach ($update['files'] as $file)
                                            <li class="break-all"><span class="font-medium capitalize text-slate-500">{{ $file['status'] }}</span> {{ $file['name'] }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

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
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $pageFindingMessage($page, $check) }}</p>
                            </article>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </section>

        @if (data_get($report->metrics, 'forms_count', 0) > 0)
            <section class="rounded-xl border bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold text-slate-900">Form activity</h2>
                <p class="mt-2 text-sm text-slate-600">In the last seven days the website received {{ data_get($report->metrics, 'legitimate_submissions', 0) }} legitimate {{ Str::plural('submission', data_get($report->metrics, 'legitimate_submissions', 0)) }} and filtered {{ data_get($report->metrics, 'spam_submissions', 0) }} as spam.</p>
            </section>
        @endif
    @endif
</main>
@endsection

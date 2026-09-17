@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ui-panel ui-section @container">
        <div class="flex flex-col gap-5 @3xl:flex-row @3xl:items-end @3xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-mono text-teal-700 text-base sm:text-sm">Website health report</p>
                    @if ($reportHistory->first()?->is($report))
                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-sm font-medium text-teal-700">Latest report</span>
                    @endif
                </div>
                <h1 class="mt-1 truncate text-2xl font-semibold tracking-tight text-slate-950">{{ $report->website->name }}</h1>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">Generated {{ $report->created_at->toDayDateTimeString() }}.</p>
            </div>
            <div class="flex flex-col gap-3 @md:flex-row @md:flex-wrap @md:items-end">
                <label class="ui-label block min-w-0 @md:min-w-72">
                    <span class="block text-base font-medium text-slate-700 sm:text-sm">Previous reports</span>
                    <select data-health-report-selector class="ui-input mt-1 block w-full" aria-label="Select a website health report">
                        @foreach ($reportHistory as $historicalReport)
                            <option value="{{ route('admin.website-health-reports.show', [$report->website, $historicalReport]) }}" @selected($historicalReport->is($report))>
                                {{ $historicalReport->created_at->format('j M Y, H:i') }} · {{ ucfirst(str_replace('_', ' ', $historicalReport->overall_status ?: $historicalReport->status)) }}{{ $loop->first ? ' · Latest' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                @if (! $reportHistory->first()?->is($report))
                    <a href="{{ route('admin.websites.section', [$report->website, 'health']) }}" class="ui-button ui-button-secondary">Open latest</a>
                @endif
                @if (auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('admin.website-health-reports.store', $report->website) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="ui-button ui-button-secondary w-full @md:w-auto">Run report again</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($aiPrompt && ! $report->website->repository)
        <section class="rounded-lg border border-violet-200 bg-violet-50 p-4 shadow-sm" aria-labelledby="ai-remediation-prompt-title">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="font-medium uppercase tracking-wide text-violet-700 text-base sm:text-sm">Administrator tool</p>
                    <h2 id="ai-remediation-prompt-title" class="mt-1 font-semibold text-violet-950">AI remediation prompt</h2>
                    <p class="mt-1 text-violet-800 text-base sm:text-sm">Copy this into your preferred AI coding assistant to plan and implement the report fixes.</p>
                </div>
                <button type="button" class="ui-button ui-button-secondary ui-button-small js-copy-text" data-copy-target="health-report-ai-prompt" data-copy-label="Copy prompt" data-copied-label="Copied">Copy prompt</button>
            </div>
            <textarea id="health-report-ai-prompt" class="ui-input mt-4 h-72 w-full resize-y font-mono leading-5" readonly>{{ $aiPrompt }}</textarea>
        </section>
    @endif

    @php
        $remediationRun = $report->remediationRuns->first();
        $hasRemediableFindings = collect($report->checks)->whereIn('status', ['warning', 'failed'])->isNotEmpty() || $report->pages->contains(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->isNotEmpty());
        $pixelEligiblePages = $report->pages->filter(fn ($page) => collect($page->checks)->whereIn('status', ['warning', 'failed'])->whereIn('key', ['page_title', 'meta_description'])->isNotEmpty());
        $reviewablePixelOptimisations = $report->pages->flatMap->optimisations->whereIn('status', [\App\Enums\OptimisationStatus::Draft, \App\Enums\OptimisationStatus::Approved]);
        $pixelRemediationAvailable = $canUsePixel && $report->website->pixel_enabled && $pixelEligiblePages->isNotEmpty();
        $githubRemediationAvailable = $report->website->repository && Auth::user()?->githubAuthorization()->exists() && $hasRemediableFindings;
        $wordpressConnected = $report->website->wordpress_enabled && $report->website->wordpressConnection?->isConnected();
        $repositoryDeliveryLabel = $wordpressConnected ? 'WordPress' : 'GitHub';
    @endphp
    @if ($canManageWebsite || $aiPrompt)
        <div class="grid gap-4 lg:grid-cols-2">
        @if ($canManageWebsite && $report->status === 'completed' && ($pixelRemediationAvailable || $githubRemediationAvailable))
            <section class="rounded-lg border border-teal-200 bg-teal-50 p-4 shadow-sm">
                <p class="font-medium uppercase tracking-wide text-teal-700 text-base sm:text-sm">Automated remediation</p>
                <h2 class="mt-1 font-semibold text-teal-950">Prepare available fixes</h2>
                @if ($pixelRemediationAvailable && $githubRemediationAvailable)
                    <p class="mt-1 text-teal-800 text-base sm:text-sm">Pixel is enabled for supported page fixes, and {{ $repositoryDeliveryLabel }} is connected for repository fixes. Nothing is published without review.</p>
                @elseif ($pixelRemediationAvailable)
                    <p class="mt-1 text-teal-800 text-base sm:text-sm">Pixel is enabled for supported page fixes. Nothing is published without review.</p>
                @else
                    <p class="mt-1 text-teal-800 text-base sm:text-sm">{{ $repositoryDeliveryLabel }} is connected for repository fixes. Nothing is published without review.</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-medium">
                    @if ($pixelRemediationAvailable)
                        <span class="rounded-full bg-white px-2.5 py-1 text-teal-800 ring-1 ring-teal-700/20">Pixel · {{ $pixelEligiblePages->count() }} {{ Str::plural('page', $pixelEligiblePages->count()) }}</span>
                    @endif
                    @if ($githubRemediationAvailable)
                        <span class="rounded-full bg-white px-2.5 py-1 text-slate-700 ring-1 ring-slate-700/20">{{ $repositoryDeliveryLabel }} · repository findings</span>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.report-remediation.store', [$report->website, $report]) }}">
                        @csrf
                        <button type="submit" class="ui-button ui-button-primary">Prepare fixes</button>
                    </form>
                    @if ($reviewablePixelOptimisations->isNotEmpty())
                        <form method="POST" action="{{ route('admin.report-optimisations.deploy', [$report->website, $report]) }}">
                            @csrf
                            <button type="submit" class="ui-button ui-button-primary">Approve &amp; deploy all ({{ $reviewablePixelOptimisations->count() }})</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
        @if ($aiPrompt)
        @if ($remediationRun && $remediationRun->status !== \App\Models\RemediationRun::STATUS_COMPLETED)
            <section class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900">
                <p class="font-medium uppercase tracking-wide text-blue-700 text-base sm:text-sm">GitHub remediation</p>
                <h2 class="mt-1 font-semibold">{{ str_replace('_', ' ', ucfirst($remediationRun->status)) }}</h2>
                <p class="mt-1 text-base sm:text-sm">{{ count($remediationRun->findings) }} selected {{ Str::plural('finding', count($remediationRun->findings)) }} for {{ $remediationRun->repository->full_name }}.</p>
                @if ($remediationRun->copilot_task_url || $remediationRun->pull_request_url)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($remediationRun->copilot_task_url)
                            <a href="{{ $remediationRun->copilot_task_url }}" class="ui-button ui-button-secondary" target="_blank" rel="noreferrer">View remediation task</a>
                        @endif
                        @if ($remediationRun->pull_request_url)
                            <a href="{{ $remediationRun->pull_request_url }}" class="ui-button ui-button-secondary" target="_blank" rel="noreferrer">View pull request</a>
                        @endif
                    </div>
                @endif
                @if (Auth::user()?->isAdmin() && $remediationRun->status === \App\Models\RemediationRun::STATUS_PULL_REQUEST_OPEN)
                    <form method="POST" action="{{ route('admin.remediation-runs.complete', [$report->website, $report, $remediationRun]) }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="ui-button ui-button-secondary">Mark as complete</button>
                        <p class="mt-2 text-blue-800 text-base sm:text-sm">Dismiss this remediation if the work is already finished. This does not change GitHub.</p>
                    </form>
                @endif
                @if ($remediationRun->error)
                    <p class="mt-3 text-red-700 text-base sm:text-sm">{{ $remediationRun->error }}</p>
                @endif
            </section>
        @elseif (! $pixelRemediationAvailable && ! $report->website->repository)
            <section class="ui-well p-4">
                <h2 class="font-semibold text-slate-900">Connect a remediation path</h2>
                <p class="mt-1 text-slate-600 text-base sm:text-sm">Enable the Sitewell Pixel for supported page fixes or connect GitHub for repository changes.</p>
                <a href="{{ route('admin.website-repositories.create', $report->website) }}" class="ui-button ui-button-primary mt-3">Connect GitHub</a>
            </section>
        @endif
        @endif
        </div>
    @endif

    @if ($report->status === 'failed')
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
            <h2 class="font-semibold">Report failed</h2>
            <p class="mt-1 text-base sm:text-sm">{{ $report->error }}</p>
        </div>
    @elseif ($report->status !== 'completed')
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-800">This report is {{ $report->status }}. Refresh after the queue has processed it.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="ui-panel p-4"><p class="font-medium uppercase tracking-wide text-slate-500 text-base sm:text-sm">Overall</p><p class="mt-2 text-xl font-semibold capitalize">{{ str_replace('_', ' ', $report->overall_status) }}</p></div>
            <div class="ui-panel p-4"><p class="font-medium uppercase tracking-wide text-slate-500 text-base sm:text-sm">Pages analysed</p><p class="mt-2 text-2xl font-semibold">{{ data_get($report->metrics, 'pages_analyzed', 0) }}</p></div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="font-medium uppercase tracking-wide text-emerald-700 text-base sm:text-sm">Passed</p><p class="mt-2 text-2xl font-semibold text-emerald-800">{{ $report->passed_checks }}</p></div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="font-medium uppercase tracking-wide text-amber-700 text-base sm:text-sm">Warnings</p><p class="mt-2 text-2xl font-semibold text-amber-800">{{ $report->warning_checks }}</p></div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4"><p class="font-medium uppercase tracking-wide text-red-700 text-base sm:text-sm">Failed</p><p class="mt-2 text-2xl font-semibold text-red-800">{{ $report->failed_checks }}</p></div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <strong>{{ data_get($report->metrics, 'changes.new_issues', 0) }} new issues</strong> and <strong>{{ data_get($report->metrics, 'changes.resolved_issues', 0) }} resolved issues</strong> since the previous completed report.
        </div>

        <x-performance-summary :report="$report" />

        <x-structured-data-summary :report="$report" />

        @if (data_get($report->metrics, 'forms_count', 0) > 0)
            <div class="ui-panel p-4">
                <h2 class="font-semibold">Last seven days of forms</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                    <div><dt class="text-slate-500">Submissions</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'submissions', 0) }}</dd></div>
                    <div><dt class="text-slate-500">Legitimate</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'legitimate_submissions', 0) }}</dd></div>
                    <div><dt class="text-slate-500">Spam</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'spam_submissions', 0) }}</dd></div>
                    <div><dt class="text-slate-500">Email failures</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'email_failures', 0) }}</dd></div>
                    <div><dt class="text-slate-500">Webhook failures</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'webhook_failures', 0) }}</dd></div>
                </dl>
            </div>
        @endif

        @foreach (collect($report->checks)->groupBy('category') as $category => $checks)
            <div class="ui-panel p-4">
                <h2 class="font-semibold capitalize">{{ $category }}</h2>
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach ($checks as $check)
                        <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-slate-900">{{ $check['label'] }}</h3>
                                <p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $check['message'] }}</p>
                            </div>
                            <span @class([
                                'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                'bg-emerald-100 text-emerald-800' => $check['status'] === 'passed',
                                'bg-amber-100 text-amber-800' => $check['status'] === 'warning',
                                'bg-red-100 text-red-800' => $check['status'] === 'failed',
                            ])>{{ $check['status'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($report->pages->isNotEmpty())
            <div class="ui-panel overflow-hidden">
                <div class="border-b border-slate-950/10 p-4">
                    <h2 class="font-semibold">Page-by-page analysis</h2>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">Technical and on-page SEO evidence for every page visited.</p>
                </div>
                <div class="divide-y divide-slate-950/10">
                    @foreach ($report->pages as $page)
                        @php
                            $issues = collect($page->checks)->whereNotIn('status', ['passed']);
                        @endphp
                        <details class="group p-4">
                            <summary class="flex cursor-pointer list-none flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900 text-base sm:text-sm">{{ parse_url($page->url, PHP_URL_PATH) ?: '/' }}</p>
                                    <p class="mt-1 truncate text-slate-500 text-base sm:text-sm">{{ $page->title ?: 'No page title' }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 font-medium',
                                        'bg-emerald-100 text-emerald-800' => $page->status_code >= 200 && $page->status_code < 300,
                                        'bg-amber-100 text-amber-800' => $page->status_code >= 300 && $page->status_code < 400,
                                        'bg-red-100 text-red-800' => $page->status_code === null || $page->status_code >= 400,
                                    ])>HTTP {{ $page->status_code ?? 'error' }}</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $page->response_time_ms ?? '—' }} ms</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{{ $page->word_count }} words</span>
                                    <span @class(['rounded-full px-2.5 py-1 font-medium', 'bg-emerald-100 text-emerald-800' => $issues->isEmpty(), 'bg-amber-100 text-amber-800' => $issues->isNotEmpty()])>{{ $issues->count() }} {{ Str::plural('issue', $issues->count()) }}</span>
                                </div>
                            </summary>
                            <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-3">
                                <dl class="space-y-2 text-sm text-slate-600">
                                    <div><dt class="font-medium text-slate-900">URL</dt><dd class="break-all"><a class="text-blue-700 hover:underline" href="{{ $page->url }}" target="_blank" rel="noreferrer">{{ $page->url }}</a></dd></div>
                                    <div><dt class="font-medium text-slate-900">Canonical</dt><dd class="break-all">{{ $page->canonical_url ?: 'Not set' }}</dd></div>
                                    <div><dt class="font-medium text-slate-900">H1 headings</dt><dd>{{ $page->h1_count }}</dd></div>
                                    <div><dt class="font-medium text-slate-900">Images missing alt text</dt><dd>{{ $page->missing_alt_count }} of {{ $page->images_count }}</dd></div>
                                </dl>
                                <div class="space-y-3 lg:col-span-2">
                                    @foreach ($page->checks as $check)
                                        @php
                                            $message = $check['message'];
                                            if ($check['status'] === 'warning' && $check['key'] === 'page_title' && Str::length((string) $page->title) > 65) {
                                                $message = 'The title is '.Str::length($page->title).' characters long. Aim for 65 or fewer so it is less likely to be truncated in search results. Current title: '.$page->title;
                                            } elseif ($check['status'] === 'warning' && $check['key'] === 'meta_description' && Str::length((string) $page->meta_description) > 170) {
                                                $message = 'The meta description is '.Str::length($page->meta_description).' characters long. Aim for 170 or fewer so it is less likely to be truncated in search results.';
                                            }
                                        @endphp
                                        <div class="flex items-start justify-between gap-3 text-sm">
                                            <div><p class="font-medium text-slate-900">{{ $check['label'] }}</p><p class="text-slate-600">{{ $message }}</p></div>
                                            <span @class(['shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize', 'bg-emerald-100 text-emerald-800' => $check['status'] === 'passed', 'bg-amber-100 text-amber-800' => $check['status'] === 'warning', 'bg-red-100 text-red-800' => $check['status'] === 'failed'])>{{ $check['status'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @if ($issues->isNotEmpty())
                                <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                                    <a href="{{ route('admin.website-health-report-pages.show', [$report->website, $report, $page]) }}" class="ui-button ui-button-primary">View SEO recommendations</a>
                                </div>
                            @endif
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection

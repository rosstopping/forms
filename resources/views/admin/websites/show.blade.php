@extends('layouts.app')

@section('content')
<div class="space-y-6" data-tabs data-default-tab="{{ request('tab', $errors->any() && $website->repository ? 'content' : 'health') }}">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ e($website->name) }}</h1>
            <p class="text-sm text-slate-600">Audit health, search visibility, content activity, forms, and submissions.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->isAdmin())
                @if ($outreachProspect)
                    <a href="{{ route('admin.prospects.show', $outreachProspect) }}" class="rounded-md bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700">View outreach prospect</a>
                @else
                    <form method="POST" action="{{ route('admin.websites.prospect.store', $website) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700">Create outreach prospect</button>
                    </form>
                @endif
            @endif
            <a href="{{ route('admin.websites.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to websites</a>
        </div>
    </div>

    <div class="website-tabs" role="tablist" aria-label="Website sections">
        <button type="button" id="website-tab-health" class="website-tab" role="tab" aria-selected="true" aria-controls="website-panel-health" tabindex="0" data-tab="health">Health reports</button>
        <button type="button" id="website-tab-search" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-search" tabindex="-1" data-tab="search">Search</button>
        <button type="button" id="website-tab-seo" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-seo" tabindex="-1" data-tab="seo">SEO Intelligence</button>
        <button type="button" id="website-tab-content" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-content" tabindex="-1" data-tab="content">Content</button>
        <button type="button" id="website-tab-pixel" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-pixel" tabindex="-1" data-tab="pixel">Pixel</button>
        <button type="button" id="website-tab-business-profile" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-business-profile" tabindex="-1" data-tab="business-profile">Business Profile</button>
        <button type="button" id="website-tab-forms" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-forms" tabindex="-1" data-tab="forms">Forms</button>
        <button type="button" id="website-tab-settings" class="website-tab" role="tab" aria-selected="false" aria-controls="website-panel-settings" tabindex="-1" data-tab="settings">Settings</button>
    </div>

    <div id="website-panel-health" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-health" data-tab-panel="health">
        @php
            $latestReport = $website->healthReports->first();
        @endphp
        <section class="rounded-lg border border-slate-200 bg-white" aria-labelledby="health-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Website monitoring</p>
                <h2 id="health-title" class="mt-1 text-lg font-semibold text-slate-950">Health reports</h2>
                <p class="mt-1 text-sm text-slate-600">Availability, on-page SEO, security headers, discoverability, and form delivery.</p>
            </div>
            <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">
                @csrf
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Run report now</button>
            </form>
        </div>
        @if ($latestReport)
            <dl class="grid grid-cols-2 gap-px bg-slate-200 @container sm:grid-cols-4">
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Latest status</dt><dd class="mt-1 text-xl font-semibold capitalize">{{ str_replace('_', ' ', $latestReport->overall_status ?: $latestReport->status) }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Passed</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-emerald-700">{{ $latestReport->passed_checks }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Warnings</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-amber-700">{{ $latestReport->warning_checks }}</dd></div>
                <div class="bg-white p-4"><dt class="truncate text-sm text-slate-500">Failed</dt><dd class="mt-1 text-xl font-semibold tabular-nums text-red-700">{{ $latestReport->failed_checks }}</dd></div>
            </dl>
        @endif
        <div class="overflow-x-auto p-4">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr class="text-left text-xs font-medium uppercase tracking-wide text-slate-500"><th class="px-3 py-2">Created</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Passed</th><th class="px-3 py-2">Warnings</th><th class="px-3 py-2">Failed</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($website->healthReports as $report)
                        <tr><td class="px-3 py-3"><a class="font-medium text-slate-950 hover:text-slate-600" href="{{ route('admin.website-health-reports.show', [$website, $report]) }}">{{ $report->created_at->toDayDateTimeString() }}</a></td><td class="px-3 py-3 capitalize text-slate-600">{{ str_replace('_', ' ', $report->overall_status ?: $report->status) }}</td><td class="px-3 py-3 tabular-nums text-emerald-700">{{ $report->passed_checks }}</td><td class="px-3 py-3 tabular-nums text-amber-700">{{ $report->warning_checks }}</td><td class="px-3 py-3 tabular-nums text-red-700">{{ $report->failed_checks }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No health reports have been generated.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </section>

    </div>

    <div id="website-panel-search" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-search" data-tab-panel="search" hidden>
        <section class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Content intelligence</p>
                    <h2 class="mt-1 font-semibold">Google Search Console</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $website->searchConsoleConnection?->property_url ?: 'Connect a property to use real search queries, clicks, impressions, and rankings.' }}</p>
                </div>
                @if ($website->searchConsoleConnection)
                    <form method="POST" action="{{ route('admin.search-console.destroy', $website) }}">@csrf @method('DELETE')<button class="rounded-md border px-3 py-2 text-sm font-medium text-slate-700">Disconnect</button></form>
                @else
                    <a href="{{ route('admin.search-console.connect', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white">Connect Google</a>
                @endif
            </div>
            @if ($searchConsoleReport)
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <h3 class="font-medium text-slate-900">Search performance</h3>
                        <div class="flex items-center gap-3 text-xs">
                            <p class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['start'])->format('j M') }}–{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['end'])->format('j M Y') }}</p>
                            <a href="{{ route('admin.search-console.performance', $website) }}" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900">View all data</a>
                        </div>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['clicks']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Impressions</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['impressions']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average CTR</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['ctr'] * 100, 1) }}%</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['position'], 1) }}</dd></div>
                    </dl>
                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <x-progress-chart title="Organic clicks" description="Actual clicks reported by Google Search Console each month." :points="$searchConsoleHistory" value-key="clicks" />
                        <x-progress-chart title="Average position" description="Impression-weighted average Google position; lower is better." :points="$searchConsoleHistory" value-key="position" format="decimal" :lower-is-better="true" />
                    </div>
                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top queries</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Query</th><th class="py-2 text-right">Clicks</th><th class="py-2 pl-3 text-right">Position</th></tr></thead><tbody>@forelse ($searchConsoleReport['queries'] as $query)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $query['query'] }}">{{ $query['query'] }}</td><td class="py-2 text-right tabular-nums">{{ number_format($query['clicks']) }}</td><td class="py-2 pl-3 text-right tabular-nums">{{ number_format($query['position'], 1) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">No query data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top pages</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Page</th><th class="py-2 text-right">Clicks</th><th class="py-2 pl-3 text-right">Position</th></tr></thead><tbody>@forelse ($searchConsoleReport['pages'] as $page)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $page['page'] }}">{{ \Illuminate\Support\Str::after($page['page'], '://') }}</td><td class="py-2 text-right tabular-nums">{{ number_format($page['clicks']) }}</td><td class="py-2 pl-3 text-right tabular-nums">{{ number_format($page['position'], 1) }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">No page data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                    </div>
                </div>
            @elseif ($searchConsoleReportUnavailable)
                <p class="mt-4 border-t border-slate-200 pt-4 text-sm text-amber-700">Search performance is temporarily unavailable. The rest of the dashboard is unaffected.</p>
            @endif
        </section>

        @include('admin.websites.partials.search-opportunities')
    </div>

    @include('admin.websites.partials.seo-intelligence', [
        'website' => $website,
        'seoGeneration' => $seoGeneration,
        'seoSnapshot' => $seoSnapshot,
        'seoKeywords' => $seoKeywords,
        'seoReferringDomains' => $seoReferringDomains,
        'seoCompetitors' => $seoCompetitors,
        'seoOpportunities' => $seoOpportunities,
        'seoFilter' => $seoFilter,
        'seoSort' => $seoSort,
        'seoDirection' => $seoDirection,
        'strikingDistanceCount' => $strikingDistanceCount,
        'canManageWebsite' => $canManageWebsite,
        'dataForSeoConfigured' => $dataForSeoConfigured,
    ])

    <div id="website-panel-content" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-content" data-tab-panel="content" hidden>
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Content publishing</p>
                    <h2 class="mt-1 font-semibold">GitHub repository</h2>
                    @if ($website->repository)
                        <p class="mt-1 text-sm text-slate-600">
                            <span class="font-medium text-slate-900">{{ $website->repository->full_name }}</span>
                            on {{ $website->repository->default_branch }}
                            @if ($website->repository->project_path)
                                · {{ $website->repository->project_path }}
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Installed for {{ $website->repository->installation->account_login }}.</p>
                    @else
                        <p class="mt-1 text-sm text-slate-600">Connect the website's source repository before adding requests or generating content.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.website-repositories.create', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ $website->repository ? 'Change repository' : 'Connect GitHub' }}</a>
                    @if ($website->repository)
                        <form method="POST" action="{{ route('admin.website-repositories.destroy', $website) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Disconnect</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if ($website->repository)
        @if (Auth::user()?->isAdmin())
        @php
            $contentPlan = $website->contentPlan;
        @endphp
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-medium uppercase tracking-wide text-slate-500">AI content</p><h2 class="mt-1 font-semibold">Weekly content generation</h2><p class="mt-1 text-sm text-slate-600">Copilot chooses a blog post, landing page, or page improvement and opens a pull request for review.</p></div>
                @if ($website->repository && $website->searchConsoleConnection?->property_url)
                    <form method="POST" action="{{ route('admin.content-generations.store', $website) }}">@csrf<button class="rounded-md border px-3 py-2 text-sm font-medium text-slate-700">Generate now</button></form>
                @endif
            </div>
            @if ($errors->has('enabled'))<p class="mt-3 text-sm text-red-700">{{ $errors->first('enabled') }}</p>@endif
            <form method="POST" action="{{ route('admin.content-plans.update', $website) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf @method('PUT')
                <label class="flex items-center gap-2 md:col-span-2"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $contentPlan?->enabled))><span class="text-sm font-medium">Generate one content PR each week</span></label>
                <div><label class="block text-sm font-medium" for="weekday">Day</label><select id="weekday" name="weekday" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">@foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $value => $day)<option value="{{ $value }}" @selected((int) old('weekday', $contentPlan?->weekday ?? 1) === $value)>{{ $day }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium" for="hour">Hour</label><select id="hour" name="hour" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('hour', $contentPlan?->hour ?? 8) === $hour)>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</option>@endfor</select></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium" for="timezone">Timezone</label><input id="timezone" name="timezone" value="{{ old('timezone', $contentPlan?->timezone ?? 'Europe/London') }}" class="mt-1 w-full rounded-md border px-3 py-2 text-sm"></div>
                <div>
                    <label class="block text-sm font-medium" for="audience">Audience</label>
                    <textarea id="audience" name="audience" rows="8" maxlength="20000" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">{{ old('audience', $contentPlan?->audience) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Up to 20,000 characters.</p>
                    @error('audience')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium" for="guidance">Editorial guidance</label>
                    <textarea id="guidance" name="guidance" rows="8" maxlength="20000" class="mt-1 w-full rounded-md border px-3 py-2 text-sm">{{ old('guidance', $contentPlan?->guidance) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Up to 20,000 characters.</p>
                    @error('guidance')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2"><button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white">Save content plan</button></div>
            </form>
            @if ($contentPlan?->generations->isNotEmpty())
                <div class="mt-5 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Date</th><th>Status</th><th>Pull request</th><th class="text-right">Actions</th></tr></thead><tbody>@foreach ($contentPlan->generations as $generation)<tr class="border-b"><td class="py-2">{{ $generation->scheduled_for->toFormattedDateString() }}</td><td>{{ str_replace('_', ' ', $generation->status) }}</td><td>@if ($generation->pull_request_url)<a class="font-medium underline" href="{{ $generation->pull_request_url }}">#{{ $generation->pull_request_number }}</a>@else — @endif</td><td><div class="flex justify-end gap-2">@if ($generation->pull_request_number && $generation->status === \App\Models\ContentGeneration::STATUS_PULL_REQUEST_OPEN)<form method="POST" action="{{ route('admin.content-generations.sync', [$website, $generation]) }}">@csrf<button class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Check GitHub status</button></form><form method="POST" action="{{ route('admin.content-generations.destroy', [$website, $generation]) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Cancel</button></form>@else — @endif</div></td></tr>@endforeach</tbody></table></div>
            @endif
        </div>
        @endif

        <section class="rounded-lg border bg-white p-4 shadow-sm" aria-labelledby="content-requests-title">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ideas for Copilot</p>
            <h2 id="content-requests-title" class="mt-1 font-semibold">Manual content requests</h2>
            <p class="mt-1 text-sm text-slate-600">Suggest a landing page, blog post, or other useful content change. The two oldest pending requests will be prioritised in the next weekly or manually started content run.</p>
        </div>

        <form method="POST" action="{{ route('admin.content-requests.store', $website) }}" class="mt-4">
            @csrf
            <label class="block text-sm font-medium text-slate-900" for="content-request-instructions">What would you like Copilot to create or change?</label>
            <textarea id="content-request-instructions" name="instructions" rows="5" maxlength="3000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="For example: Create a new landing page or blog post for a specific category.">{{ old('instructions') }}</textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500">Include the audience, useful keywords, desired location in the site, and any claims or qualifications Copilot must preserve. Up to 3,000 characters.</p>
                    @error('instructions')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add content request</button>
            </div>
        </form>

        @php
            $pendingContentRequests = $website->contentRequests->whereNull('picked_up_at');
            $actionedContentRequests = $website->contentRequests->whereNotNull('picked_up_at');
        @endphp
        <div class="mt-5 border-t border-slate-200 pt-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Pending todos</h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium tabular-nums text-amber-800">{{ $pendingContentRequests->count() }}</span>
            </div>
            <div class="mt-3 space-y-3">
                @forelse ($pendingContentRequests as $contentRequest)
                    <article class="rounded-lg border border-slate-200 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">Pending</span>
                                    <span class="text-xs text-slate-500">Added {{ $contentRequest->created_at->diffForHumans() }}{{ $contentRequest->creator ? ' by '.$contentRequest->creator->name : '' }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $contentRequest->instructions }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.content-requests.destroy', [$website, $contentRequest]) }}" class="shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Remove</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No pending content todos.</p>
                @endforelse
            </div>
        </div>

        @if ($actionedContentRequests->isNotEmpty())
            <div class="mt-5 border-t border-slate-200 pt-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Actioned todos</h3>
                        <p class="mt-1 text-xs text-slate-500">Requests already handed to Copilot remain here as a permanent activity record.</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium tabular-nums text-emerald-800">{{ $actionedContentRequests->count() }}</span>
                </div>
                <div class="mt-3 space-y-3">
                    @foreach ($actionedContentRequests as $contentRequest)
                        <article class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">Actioned</span>
                                        @if ($contentRequest->generation)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $contentRequest->generation->status) }}</span>
                                        @endif
                                        <span class="text-xs text-slate-500">Picked up {{ $contentRequest->picked_up_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $contentRequest->instructions }}</p>
                                </div>
                                @if ($contentRequest->generation?->pull_request_url)
                                    <a href="{{ $contentRequest->generation->pull_request_url }}" target="_blank" rel="noreferrer" class="shrink-0 rounded-md border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100">View pull request</a>
                                @elseif ($contentRequest->generation?->copilot_task_url)
                                    <a href="{{ $contentRequest->generation->copilot_task_url }}" target="_blank" rel="noreferrer" class="shrink-0 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View Copilot task</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
        </section>
        @endif
    </div>

    @include('admin.websites.partials.pixel', [
        'website' => $website,
        'pixelInstallationSnippet' => $pixelInstallationSnippet,
    ])

    @include('admin.websites.partials.business-profile')

    <div id="website-panel-settings" class="grid gap-6 lg:grid-cols-2" role="tabpanel" aria-labelledby="website-tab-settings" data-tab-panel="settings" hidden>
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Overview</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ $website->is_active ? 'Active' : 'Disabled' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Auto discovered</dt><dd class="font-medium">{{ $website->auto_discovered ? 'Yes' : 'No' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email notifications</dt><dd class="font-medium">{{ $website->email_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Webhook notifications</dt><dd class="font-medium">{{ $website->webhook_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Weekly health reports</dt><dd class="font-medium">{{ $website->health_reports_enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Owner</dt><dd class="font-medium">{{ $website->owner?->name ?: 'Unassigned' }}</dd></div>
            </dl>

            @if (Auth::user()?->isAdmin())
                <form method="POST" action="{{ route('admin.websites.update', $website) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="name">Website name</label>
                        <input id="name" name="name" type="text" required value="{{ old('name', $website->name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="user_id">Assign owner</label>
                        <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Unassigned</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected($website->user_id === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                        <input type="hidden" name="health_reports_enabled" value="0">
                        <input type="checkbox" name="health_reports_enabled" value="1" class="mt-1 rounded border-slate-300" @checked($website->health_reports_enabled)>
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Send weekly website health reports</span>
                            <span class="block text-xs text-slate-500">Reports are emailed to administrators and the assigned owner.</span>
                        </span>
                    </label>
                    <div class="space-y-3 rounded-lg border border-slate-200 p-3">
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="webhook_enabled" value="0">
                            <input type="checkbox" name="webhook_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('webhook_enabled', $website->webhook_enabled))>
                            <span>
                                <span class="block text-sm font-medium text-slate-900">Send submissions to a webhook</span>
                                <span class="block text-xs text-slate-500">New form submissions will be posted to the URL below unless a form has its own override.</span>
                            </span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="webhook_url">Webhook URL</label>
                            <input id="webhook_url" name="webhook_url" type="url" value="{{ old('webhook_url', $website->webhook_url) }}" placeholder="https://example.com/webhooks/forms" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            @error('webhook_url')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="webhook_secret">Webhook secret</label>
                            <input id="webhook_secret" name="webhook_secret" type="text" value="{{ old('webhook_secret', $website->webhook_secret) }}" autocomplete="off" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Optional secret used to sign webhook requests.</p>
                            @error('webhook_secret')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save settings</button>
                </form>

                <div class="mt-6 border-t border-red-200 pt-5">
                    <h3 class="text-sm font-semibold text-red-900">Delete website</h3>
                    <p class="mt-1 text-sm text-red-700">This permanently deletes the website, its forms, submissions, reports, and content settings.</p>
                    <form method="POST" action="{{ route('admin.websites.destroy', $website) }}" class="mt-3" onsubmit="return confirm('Delete this website and all of its data? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete website</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Domains</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($website->domains as $domain)
                    <li class="flex items-center justify-between">
                        <span>{{ e($domain->domain) }}</span>
                        <span class="text-slate-500">{{ $domain->is_primary ? 'Primary' : 'Alias' }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No domains recorded.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold">Website users</h2>
                    <p class="mt-1 text-sm text-slate-600">Managers can make changes. Viewers have read-only access.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $website->members->count() + ($website->owner ? 1 : 0) }} users</span>
            </div>

            <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                @if ($website->owner)
                    <div class="flex items-center justify-between gap-4 p-3">
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $website->owner->name }}</p><p class="truncate text-xs text-slate-500">{{ $website->owner->email }}</p></div>
                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700">Owner</span>
                    </div>
                @endif
                @foreach ($website->members as $member)
                    <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $member->name }}</p><p class="truncate text-xs text-slate-500">{{ $member->email }}</p></div>
                        @if ($canManageMembers)
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.websites.members.update', [$website, $member]) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                        <option value="manager" @selected($member->pivot->role === 'manager')>Manager</option>
                                        <option value="viewer" @selected($member->pivot->role === 'viewer')>Viewer</option>
                                    </select>
                                    <button class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.websites.members.destroy', [$website, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Remove</button>
                                </form>
                            </div>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-700">{{ $member->pivot->role }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($canManageMembers && $availableMembers->isNotEmpty())
                <form method="POST" action="{{ route('admin.websites.members.store', $website) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                    @csrf
                    <div><label for="member_user_id" class="text-sm font-medium text-slate-700">Add user</label><select id="member_user_id" name="user_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Choose a user</option>@foreach ($availableMembers as $availableMember)<option value="{{ $availableMember->id }}">{{ $availableMember->name }} ({{ $availableMember->email }})</option>@endforeach</select></div>
                    <div><label for="member_role" class="text-sm font-medium text-slate-700">Access</label><select id="member_role" name="role" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="manager">Manager</option><option value="viewer">Viewer</option></select></div>
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add user</button>
                </form>
            @elseif ($canManageMembers)
                <p class="mt-4 text-sm text-slate-500">Every available user already has access to this website.</p>
            @endif
        </div>
    </div>

    <div id="website-panel-forms" class="grid gap-6 lg:grid-cols-2" role="tabpanel" aria-labelledby="website-tab-forms" data-tab-panel="forms" hidden>
        <section class="@container rounded-lg border border-blue-200 bg-blue-50 p-4 lg:col-span-2" aria-labelledby="form-onboarding-title">
            <div class="grid gap-5 @4xl:grid-cols-[2fr_3fr] @4xl:gap-6">
                <div class="min-w-0">
                    <p class="font-mono text-sm font-medium uppercase tracking-wide text-blue-700">Form setup</p>
                    <h2 id="form-onboarding-title" class="mt-1 text-xl font-semibold text-balance text-blue-950">Connect a website form</h2>
                    <p class="mt-2 text-base text-pretty text-blue-900 sm:text-sm">Paste the example into the website, then replace or add the fields you need. Submissions from {{ e($website->domains->firstWhere('is_primary', true)?->domain ?? $website->domains->first()?->domain ?? 'this website') }} will be matched automatically.</p>

                    <dl class="mt-5 grid gap-4">
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">1. Post to the shared endpoint</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Use <code class="font-mono">POST</code> and the action shown in the example. No API key, website ID, or CSRF field is required.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">2. Give the form a stable name</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Set <code class="font-mono">_form_name</code> to a clear name such as “Contact form”. Keep it unchanged after launch; a different name may create a separate form.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">3. Keep the honeypot empty</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Include the hidden <code class="font-mono">_honeypot</code> field. Visitors will not see it, while bots that fill it will be recorded as spam without triggering notifications.</dd>
                        </div>
                    </dl>
                </div>

                <div class="min-w-0 rounded-md bg-slate-950 p-3 [--padding:--spacing(3)] [--radius:var(--radius-md)]">
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <p class="min-w-0 truncate font-mono text-sm text-slate-300">HTML · complete example</p>
                        <button type="button" class="js-copy-text relative shrink-0 rounded-md border border-white/15 bg-white/10 px-3 py-2 text-base font-medium text-white hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:text-sm" data-copy-target="form-onboarding-example" data-copy-label="Copy example" data-copied-label="Copied">Copy example<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
                    </div>
                    <textarea id="form-onboarding-example" class="h-96 w-full resize-y rounded-[calc(var(--radius)-var(--padding))] border-0 bg-slate-900 p-3 font-mono text-sm text-slate-100 focus:outline-2 focus:outline-offset-2 focus:outline-blue-400" readonly spellcheck="false">&lt;form method="POST" action="{{ route('forms.submit') }}"&gt;
    &lt;input type="hidden" name="_form_name" value="Contact form"&gt;

    &lt;div
        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
        aria-hidden="true"
    &gt;
        &lt;label&gt;
            Leave this field empty
            &lt;input
                type="text"
                name="_honeypot"
                tabindex="-1"
                autocomplete="off"
            &gt;
        &lt;/label&gt;
    &lt;/div&gt;

    &lt;label&gt;
        Name
        &lt;input type="text" name="name" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Email
        &lt;input type="email" name="email" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Message
        &lt;textarea name="message" required&gt;&lt;/textarea&gt;
    &lt;/label&gt;

    &lt;button type="submit"&gt;Send enquiry&lt;/button&gt;
&lt;/form&gt;</textarea>
                </div>
            </div>
        </section>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 shadow-sm lg:col-span-2">
            <h2 class="font-semibold text-blue-950">Automatic customer reply</h2>
            <p class="mt-1 text-sm text-blue-800">Set the website-wide acknowledgement. Individual forms can inherit or override it.</p>
            <form method="POST" action="{{ route('admin.websites.autoresponder.update', $website) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="autoresponder_enabled" value="0">
                <label class="flex items-start gap-3 rounded-lg border border-blue-200 bg-white p-3">
                    <input type="checkbox" name="autoresponder_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('autoresponder_enabled', $website->autoresponder_enabled))>
                    <span><span class="block text-sm font-medium text-slate-900">Automatically acknowledge new enquiries</span><span class="block text-xs text-slate-500">Only sends when a valid customer email is present and the submission passes spam checks.</span></span>
                </label>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="autoresponder_subject">Email subject</label>
                        <input id="autoresponder_subject" name="autoresponder_subject" value="{{ old('autoresponder_subject', $website->autoresponder_subject) }}" placeholder="We've received your {form_name} enquiry" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="lg:row-span-2">
                        <label class="text-sm font-medium text-slate-700" for="autoresponder_body">Email message</label>
                        <textarea id="autoresponder_body" name="autoresponder_body" rows="7" placeholder="Hi {name},&#10;&#10;Thanks for contacting {website_name}. We've received your enquiry and someone from our team will get back to you soon." class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('autoresponder_body', $website->autoresponder_body) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">Available placeholders: {name}, {form_name}, {website_name}, {website_domain}, {submission_id}</p>
                    </div>
                    <div><button class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Save automatic reply</button></div>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-lg border bg-white shadow-sm lg:col-span-2">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Name</th><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Website</th><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Submissions</th><th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Seen</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($website->forms as $form)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-3"><a href="{{ route('admin.forms.show', $form) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($form->name) }}</a></td><td class="px-4 py-3 text-sm text-slate-600">{{ e($website->name) }}</td><td class="px-4 py-3 text-sm text-slate-600">{{ $form->is_active ? 'Active' : 'Disabled' }}</td><td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $form->submissions_count }}</td><td class="px-4 py-3 text-sm text-slate-500">{{ $form->created_at?->diffForHumans() }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No forms registered for this website.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

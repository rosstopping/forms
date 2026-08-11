@extends('layouts.app')

@section('content')
<div class="space-y-6" data-tabs data-default-tab="{{ $errors->any() ? 'content' : 'health' }}">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ e($website->name) }}</h1>
            <p class="text-sm text-slate-600">Audit health, search visibility, content activity, forms, and submissions.</p>
        </div>
        <a href="{{ route('admin.websites.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to websites</a>
    </div>

    <div class="flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Website sections">
        <button type="button" id="website-tab-health" class="shrink-0 border-b-2 border-slate-900 px-3 py-2 text-sm font-medium text-slate-950" role="tab" aria-selected="true" aria-controls="website-panel-health" tabindex="0" data-tab="health">Health reports</button>
        @if (Auth::user()?->isAdmin())
            <button type="button" id="website-tab-search" class="shrink-0 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-950" role="tab" aria-selected="false" aria-controls="website-panel-search" tabindex="-1" data-tab="search">Search</button>
        @endif
        <button type="button" id="website-tab-content" class="shrink-0 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-950" role="tab" aria-selected="false" aria-controls="website-panel-content" tabindex="-1" data-tab="content">Content</button>
        <button type="button" id="website-tab-forms" class="shrink-0 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-950" role="tab" aria-selected="false" aria-controls="website-panel-forms" tabindex="-1" data-tab="forms">Forms & submissions</button>
        <button type="button" id="website-tab-settings" class="shrink-0 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-950" role="tab" aria-selected="false" aria-controls="website-panel-settings" tabindex="-1" data-tab="settings">Settings</button>
    </div>

    <div id="website-panel-health" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-health" data-tab-panel="health">
        @php($latestReport = $website->healthReports->first())
        <section class="rounded-lg border border-slate-200 bg-white" aria-labelledby="health-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Website monitoring</p>
                <h2 id="health-title" class="mt-1 text-lg font-semibold text-slate-950">Health reports</h2>
                <p class="mt-1 text-sm text-slate-600">Availability, on-page SEO, security headers, discoverability, and form delivery.</p>
            </div>
            @if (Auth::user()?->isAdmin())
                <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Run report now</button>
                </form>
            @endif
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

        @if (Auth::user()?->isAdmin())
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Automated remediation</p>
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
                        <p class="mt-1 text-sm text-slate-600">Connect the source repository to prepare tracked fixes from health-report findings.</p>
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
        @endif
    </div>

    @if (Auth::user()?->isAdmin())
        <div id="website-panel-search" class="rounded-lg border bg-white p-4 shadow-sm" role="tabpanel" aria-labelledby="website-tab-search" data-tab-panel="search" hidden>
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
                        <p class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['start'])->format('j M') }}–{{ \Illuminate\Support\Carbon::parse($searchConsoleReport['period']['end'])->format('j M Y') }}</p>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Clicks</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['clicks']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Impressions</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['impressions']) }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average CTR</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['ctr'] * 100, 1) }}%</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Average position</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($searchConsoleReport['totals']['position'], 1) }}</dd></div>
                    </dl>
                    <div class="mt-5 grid gap-5 lg:grid-cols-2">
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top queries</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Query</th><th class="py-2 text-right">Clicks</th></tr></thead><tbody>@forelse ($searchConsoleReport['queries'] as $query)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $query['query'] }}">{{ $query['query'] }}</td><td class="py-2 text-right">{{ number_format($query['clicks']) }}</td></tr>@empty<tr><td colspan="2" class="py-3 text-slate-500">No query data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                        <div class="overflow-x-auto">
                            <h4 class="text-sm font-medium text-slate-900">Top pages</h4>
                            <table class="mt-2 min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Page</th><th class="py-2 text-right">Clicks</th></tr></thead><tbody>@forelse ($searchConsoleReport['pages'] as $page)<tr class="border-b border-slate-100"><td class="max-w-64 truncate py-2 pr-3" title="{{ $page['page'] }}">{{ \Illuminate\Support\Str::after($page['page'], '://') }}</td><td class="py-2 text-right">{{ number_format($page['clicks']) }}</td></tr>@empty<tr><td colspan="2" class="py-3 text-slate-500">No page data yet.</td></tr>@endforelse</tbody></table>
                        </div>
                    </div>
                </div>
            @elseif ($searchConsoleReportUnavailable)
                <p class="mt-4 border-t border-slate-200 pt-4 text-sm text-amber-700">Search performance is temporarily unavailable. The rest of the dashboard is unaffected.</p>
            @endif
        </div>
    @endif

    <div id="website-panel-content" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-content" data-tab-panel="content" hidden>
        @if (Auth::user()?->isAdmin())
        @php($contentPlan = $website->contentPlan)
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
                <div class="mt-5 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Date</th><th>Status</th><th>Pull request</th></tr></thead><tbody>@foreach ($contentPlan->generations as $generation)<tr class="border-b"><td class="py-2">{{ $generation->scheduled_for->toFormattedDateString() }}</td><td>{{ str_replace('_', ' ', $generation->status) }}</td><td>@if ($generation->pull_request_url)<a class="font-medium underline" href="{{ $generation->pull_request_url }}">#{{ $generation->pull_request_number }}</a>@else — @endif</td></tr>@endforeach</tbody></table></div>
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
            <textarea id="content-request-instructions" name="instructions" rows="5" maxlength="3000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="For example: We are getting traffic for ‘love island villa tenerife’. Create a landing page under the villa navigation that answers this search while making clear we are not the official villa.">{{ old('instructions') }}</textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500">Include the audience, useful keywords, desired location in the site, and any claims or qualifications Copilot must preserve. Up to 3,000 characters.</p>
                    @error('instructions')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add content request</button>
            </div>
        </form>

        <div class="mt-5 border-t border-slate-200 pt-4">
            <h3 class="text-sm font-semibold text-slate-900">Recent requests</h3>
            <div class="mt-3 space-y-3">
                @forelse ($website->contentRequests as $contentRequest)
                    <article class="rounded-lg border border-slate-200 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span @class(['rounded-full px-2.5 py-1 text-xs font-medium', 'bg-amber-100 text-amber-800' => ! $contentRequest->picked_up_at, 'bg-emerald-100 text-emerald-800' => $contentRequest->picked_up_at])>{{ $contentRequest->picked_up_at ? 'Picked up' : 'Pending' }}</span>
                                    <span class="text-xs text-slate-500">Added {{ $contentRequest->created_at->diffForHumans() }}{{ $contentRequest->creator ? ' by '.$contentRequest->creator->name : '' }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $contentRequest->instructions }}</p>
                            </div>
                            @if (! $contentRequest->picked_up_at)
                                <form method="POST" action="{{ route('admin.content-requests.destroy', [$website, $contentRequest]) }}" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Remove</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No manual content requests have been added yet.</p>
                @endforelse
            </div>
        </div>
        </section>
    </div>

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
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Save settings</button>
                </form>
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
    </div>

    <div id="website-panel-forms" class="grid gap-6 lg:grid-cols-2" role="tabpanel" aria-labelledby="website-tab-forms" data-tab-panel="forms" hidden>
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Forms</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($website->forms as $form)
                    <li class="flex items-center justify-between">
                        <a href="{{ route('admin.forms.show', $form) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($form->name) }}</a>
                        <span class="text-slate-500">{{ $form->is_active ? 'Active' : 'Disabled' }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No forms registered for this website.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Recent submissions</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($website->submissions as $submission)
                    <li class="flex items-center justify-between">
                        <a href="{{ route('admin.form-submissions.show', $submission) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($submission->source_domain ?: 'Unknown') }}</a>
                        <span class="text-slate-500">{{ $submission->created_at?->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No submissions yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

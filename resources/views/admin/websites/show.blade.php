@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ e($website->name) }}</h1>
            <p class="text-sm text-slate-600">Website details and recent activity.</p>
        </div>
        <a href="{{ route('admin.websites.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to websites</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
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

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold">Website health reports</h2>
                <p class="text-sm text-slate-600">Availability, on-page SEO, security headers, discoverability, and form delivery.</p>
            </div>
            @if (Auth::user()?->isAdmin())
                <form method="POST" action="{{ route('admin.website-health-reports.store', $website) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Run report now</button>
                </form>
            @endif
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Created</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Passed</th>
                        <th class="px-3 py-2">Warnings</th>
                        <th class="px-3 py-2">Failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($website->healthReports as $report)
                        <tr>
                            <td class="px-3 py-3"><a class="font-medium text-slate-900 hover:text-slate-600" href="{{ route('admin.website-health-reports.show', [$website, $report]) }}">{{ $report->created_at->toDayDateTimeString() }}</a></td>
                            <td class="px-3 py-3 capitalize text-slate-600">{{ str_replace('_', ' ', $report->overall_status ?: $report->status) }}</td>
                            <td class="px-3 py-3 text-emerald-700">{{ $report->passed_checks }}</td>
                            <td class="px-3 py-3 text-amber-700">{{ $report->warning_checks }}</td>
                            <td class="px-3 py-3 text-red-700">{{ $report->failed_checks }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No health reports have been generated.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
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

@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Websites</h1>
            <p class="text-sm text-slate-600">Monitor audit health, search performance, content, and form activity for each website.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('admin.websites.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">Add website</a>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Latest audit</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Findings</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Forms</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Submissions</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Last checked</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($websites as $website)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.websites.show', $website) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($website->name) }}</a>
                        </td>
                        @php($report = $website->latestHealthReport)
                        <td class="px-4 py-3 text-sm">
                            <span @class(['rounded-full px-2 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-700' => $report?->overall_status === 'healthy', 'bg-amber-50 text-amber-700' => $report?->overall_status === 'needs_attention', 'bg-red-50 text-red-700' => $report?->overall_status === 'critical', 'bg-slate-100 text-slate-600' => ! $report || ! in_array($report->overall_status, ['healthy', 'needs_attention', 'critical'], true)])>{{ $report ? ucfirst(str_replace('_', ' ', $report->overall_status ?: $report->status)) : 'Not audited' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">@if ($report)<span class="tabular-nums text-red-700">{{ $report->failed_checks }} failed</span> · <span class="tabular-nums text-amber-700">{{ $report->warning_checks }} warnings</span>@else — @endif</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $website->forms_count }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-slate-600">{{ $website->submissions_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $report?->created_at?->diffForHumans() ?: 'Never' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No websites yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $websites->links() }}
</div>
@endsection

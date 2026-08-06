@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Website health report</p>
            <h1 class="text-2xl font-semibold">{{ $report->website->name }}</h1>
            <p class="text-sm text-slate-600">Created {{ $report->created_at->toDayDateTimeString() }}</p>
        </div>
        <a href="{{ route('admin.websites.show', $report->website) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to website</a>
    </div>

    @if ($report->status === 'failed')
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
            <h2 class="font-semibold">Report failed</h2>
            <p class="mt-1 text-sm">{{ $report->error }}</p>
        </div>
    @elseif ($report->status !== 'completed')
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-800">This report is {{ $report->status }}. Refresh after the queue has processed it.</div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border bg-white p-4 shadow-sm"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Overall</p><p class="mt-2 text-xl font-semibold capitalize">{{ str_replace('_', ' ', $report->overall_status) }}</p></div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Passed</p><p class="mt-2 text-2xl font-semibold text-emerald-800">{{ $report->passed_checks }}</p></div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-amber-700">Warnings</p><p class="mt-2 text-2xl font-semibold text-amber-800">{{ $report->warning_checks }}</p></div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-red-700">Failed</p><p class="mt-2 text-2xl font-semibold text-red-800">{{ $report->failed_checks }}</p></div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <strong>{{ data_get($report->metrics, 'changes.new_issues', 0) }} new issues</strong> and <strong>{{ data_get($report->metrics, 'changes.resolved_issues', 0) }} resolved issues</strong> since the previous completed report.
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Last seven days of forms</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                <div><dt class="text-slate-500">Submissions</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'submissions', 0) }}</dd></div>
                <div><dt class="text-slate-500">Legitimate</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'legitimate_submissions', 0) }}</dd></div>
                <div><dt class="text-slate-500">Spam</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'spam_submissions', 0) }}</dd></div>
                <div><dt class="text-slate-500">Email failures</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'email_failures', 0) }}</dd></div>
                <div><dt class="text-slate-500">Webhook failures</dt><dd class="mt-1 text-xl font-semibold">{{ data_get($report->metrics, 'webhook_failures', 0) }}</dd></div>
            </dl>
        </div>

        @foreach (collect($report->checks)->groupBy('category') as $category => $checks)
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <h2 class="font-semibold capitalize">{{ $category }}</h2>
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach ($checks as $check)
                        <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-slate-900">{{ $check['label'] }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $check['message'] }}</p>
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
    @endif
</div>
@endsection

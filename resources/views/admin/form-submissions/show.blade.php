@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Submission details</h1>
            <p class="text-sm text-slate-600">Inspect the submitted data and source information.</p>
        </div>
        <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to submissions</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Submission</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Source domain</dt><dd class="font-medium">{{ e($formSubmission->source_domain ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Source URL</dt><dd class="font-medium break-all">{{ e($formSubmission->source_url ?: 'Unknown') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Form</dt><dd class="font-medium">{{ e($formSubmission->form?->name ?: 'Unknown form') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ e($formSubmission->website?->name ?: 'Unknown website') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Spam</dt><dd class="font-medium">{{ $formSubmission->is_spam ? 'Yes' : 'No' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Submission data</h2>
            <pre class="mt-3 overflow-x-auto rounded bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($formSubmission->data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</div>
@endsection

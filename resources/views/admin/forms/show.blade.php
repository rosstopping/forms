@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ e($form->name) }}</h1>
            <p class="text-sm text-slate-600">Form details and recent submissions.</p>
        </div>
        <a href="{{ route('admin.forms.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to forms</a>
    </div>

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Overview</h2>
        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
            <div class="flex justify-between"><dt class="text-slate-500">Slug</dt><dd class="font-medium">{{ e($form->slug) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="font-medium">{{ e($form->website?->name ?: 'Unknown') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium">{{ $form->is_active ? 'Active' : 'Disabled' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Auto discovered</dt><dd class="font-medium">{{ $form->auto_discovered ? 'Yes' : 'No' }}</dd></div>
        </dl>
    </div>

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Recent submissions</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($form->submissions as $submission)
                <li class="flex items-center justify-between">
                    <a href="{{ route('admin.form-submissions.show', $submission) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ e($submission->source_domain ?: 'Unknown') }}</a>
                    <span class="text-slate-500">{{ $submission->created_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li class="text-slate-500">No submissions for this form yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="space-y-6">
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
            </dl>
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

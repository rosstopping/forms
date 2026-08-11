@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Leads</h1>
        <p class="text-sm text-slate-600">Find enquiries, assign owners, and keep every follow-up moving.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $status => $label)
            <a href="{{ route('admin.form-submissions.index', ['status' => $status]) }}" class="rounded-lg border bg-white p-4 shadow-sm hover:border-slate-400">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary[$status] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="grid gap-3 rounded-lg border bg-white p-4 shadow-sm md:grid-cols-2 lg:grid-cols-5">
        <input name="search" value="{{ request('search') }}" placeholder="Search name, email or message" class="rounded-md border border-slate-300 px-3 py-2 text-sm lg:col-span-2">
        <select name="status" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">All statuses</option>@foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="website_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">All websites</option>@foreach ($websites as $website)<option value="{{ $website->id }}" @selected((string) request('website_id') === (string) $website->id)>{{ $website->name }}</option>@endforeach</select>
        <select name="assigned_to" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Any owner</option><option value="unassigned" @selected(request('assigned_to') === 'unassigned')>Unassigned</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) request('assigned_to') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
        <select name="spam" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="exclude" @selected(request('spam', 'exclude') === 'exclude')>Hide spam</option><option value="all" @selected(request('spam') === 'all')>Include spam</option><option value="only" @selected(request('spam') === 'only')>Spam only</option></select>
        <div class="flex gap-2 lg:col-span-4"><button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter leads</button><a href="{{ route('admin.form-submissions.index', ['reset_filters' => 1]) }}" class="rounded-md border px-4 py-2 text-sm font-medium text-slate-700">Clear</a></div>
    </form>

    <div class="overflow-hidden rounded-lg border bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse ($submissions as $submission)
                <a href="{{ route('admin.form-submissions.show', $submission) }}" class="grid gap-2 p-4 hover:bg-slate-50 md:grid-cols-[2fr_1fr_1fr_auto] md:items-center">
                    <div class="min-w-0"><div class="font-medium text-slate-900">{{ $submission->displayName() }}</div><div class="truncate text-sm text-slate-500">{{ $submission->replyToEmail() ?: $submission->messageExcerpt() ?: 'No contact details supplied' }}</div></div>
                    <div class="text-sm"><div class="text-slate-700">{{ $submission->form?->name ?: 'Unknown form' }}</div><div class="text-xs text-slate-500">{{ $submission->website?->name }}</div></div>
                    <div class="text-sm"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $submission->resolvedStatusLabel() }}</span><div class="mt-2 text-xs text-slate-500">{{ $submission->assignee?->name ?: 'Unassigned' }}</div></div>
                    <div class="text-xs text-slate-500 md:text-right">{{ $submission->created_at?->diffForHumans() }}</div>
                </a>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">No leads match these filters.</div>
            @endforelse
        </div>
    </div>
    {{ $submissions->links() }}
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Leads</h1>
        <p class="text-sm text-slate-600">Find enquiries, assign owners, and keep every follow-up moving.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

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
        {{-- <select name="assigned_to" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Any owner</option><option value="unassigned" @selected(request('assigned_to') === 'unassigned')>Unassigned</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) request('assigned_to') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select> --}}
        <select name="spam" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="exclude" @selected(request('spam', 'exclude') === 'exclude')>Hide spam</option><option value="all" @selected(request('spam') === 'all')>Include spam</option><option value="only" @selected(request('spam') === 'only')>Spam only</option></select>
        <div class="flex gap-2 lg:col-span-4"><button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter leads</button><a href="{{ route('admin.form-submissions.index', ['reset_filters' => 1]) }}" class="rounded-md border px-4 py-2 text-sm font-medium text-slate-700">Clear</a></div>
    </form>

    <form method="POST" action="{{ route('admin.form-submissions.bulk') }}" data-bulk-leads-form data-bulk-leads-total="{{ $bulkSelectableCount }}" class="overflow-hidden rounded-lg border bg-white shadow-sm">
        @csrf
        @method('PATCH')
        <input type="hidden" name="action" data-bulk-leads-action>
        <input type="hidden" name="selection_scope" value="page" data-bulk-leads-scope>
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="filter_status" value="{{ request('status') }}">
        <input type="hidden" name="website_id" value="{{ request('website_id') }}">
        <input type="hidden" name="assigned_to" value="{{ request('assigned_to') }}">
        <input type="hidden" name="spam" value="{{ request('spam', 'exclude') }}">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center">
            <div class="flex flex-wrap items-center gap-3">
                <div data-bulk-leads-selection-control class="relative flex items-center gap-1">
                    <input type="checkbox" data-bulk-leads-select-all aria-label="Select leads on this page" class="size-5 rounded border-slate-300">
                    <button type="button" data-bulk-leads-selection-toggle aria-label="Choose selection scope" aria-expanded="false" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-slate-200">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div data-bulk-leads-selection-menu class="absolute left-0 top-full z-20 mt-2 hidden w-72 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        <button type="button" data-bulk-leads-select-page class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-slate-700 hover:bg-slate-100">
                            <span data-bulk-leads-page-indicator class="grid size-5 place-items-center rounded border border-slate-300 text-xs text-white">✓</span>
                            <span class="flex-1 text-sm font-medium">Select this page</span>
                            <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-sm font-semibold tabular-nums text-sky-800">{{ $bulkPageSelectableCount }}</span>
                        </button>
                        <button type="button" data-bulk-leads-select-matching class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-slate-700 hover:bg-slate-100">
                            <span data-bulk-leads-all-indicator class="grid size-5 place-items-center rounded border border-slate-300 text-xs text-white">✓</span>
                            <span class="flex-1 text-sm font-medium">Select all</span>
                            <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-sm font-semibold tabular-nums text-sky-800">{{ $bulkSelectableCount }}</span>
                        </button>
                    </div>
                </div>
            </div>
            <div data-bulk-leads-actions class="hidden flex-1 flex-wrap items-center gap-2 sm:justify-end">
                <span class="text-sm text-slate-600"><span data-bulk-leads-count>0</span> selected</span>
                <div class="relative">
                    <button type="button" data-bulk-leads-menu-toggle aria-label="Lead actions" aria-expanded="false" class="grid size-9 place-items-center rounded-md border border-slate-300 bg-white text-lg font-bold tracking-widest text-slate-600 hover:bg-slate-100">•••</button>
                    <div data-bulk-leads-menu class="absolute right-0 z-20 mt-2 hidden w-64 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Lead actions</p>
                        <button type="button" data-bulk-leads-open="update_status" class="flex w-full rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-100">Update status</button>
                        <button type="button" data-bulk-leads-open="mark_spam" class="flex w-full rounded-lg px-3 py-2.5 text-left text-sm text-amber-800 hover:bg-amber-50">Mark as spam</button>
                        <button type="button" data-bulk-leads-open="delete" class="flex w-full rounded-lg px-3 py-2.5 text-left text-sm text-red-700 hover:bg-red-50">Delete</button>
                    </div>
                </div>
            </div>
        </div>
        @error('submission_ids')<p class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="divide-y divide-slate-100">
            @forelse ($submissions as $submission)
                <div class="flex items-center gap-3 p-4 hover:bg-slate-50">
                    @if ($manageableWebsiteIds->contains($submission->website_id))
                        <input type="checkbox" name="submission_ids[]" value="{{ $submission->id }}" aria-label="Select {{ $submission->displayName() }}" data-bulk-leads-checkbox class="rounded border-slate-300">
                    @else
                        <span class="size-4 shrink-0" title="Read-only lead"></span>
                    @endif
                    <a href="{{ route('admin.form-submissions.show', $submission) }}" class="grid min-w-0 flex-1 gap-2 md:grid-cols-[2fr_1fr_1fr_auto] md:items-center">
                        <div class="min-w-0"><div class="font-medium text-slate-900">{{ $submission->displayName() }}</div><div class="truncate text-sm text-slate-500">{{ $submission->replyToEmail() ?: $submission->messageExcerpt() ?: 'No contact details supplied' }}</div></div>
                        <div class="text-sm"><div class="text-slate-700">{{ $submission->form?->name ?: 'Unknown form' }}</div><div class="text-xs text-slate-500">{{ $submission->website?->name }}</div></div>
                        <div class="text-sm"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $submission->resolvedStatusLabel() }}</span><div class="mt-2 text-xs text-slate-500">{{ $submission->assignee?->name ?: 'Unassigned' }}</div></div>
                        <div class="text-xs text-slate-500 md:text-right">{{ $submission->created_at?->diffForHumans() }}</div>
                    </a>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">No leads match these filters.</div>
            @endforelse
        </div>

        <dialog data-bulk-leads-dialog class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
            <div class="p-5">
                <h2 data-bulk-leads-dialog-title class="text-lg font-semibold text-slate-950">Confirm bulk action</h2>
                <p data-bulk-leads-dialog-message class="mt-2 text-sm text-slate-600"></p>
                <div data-bulk-leads-status-field class="mt-4 hidden">
                    <label for="bulk_lead_status" class="text-sm font-medium text-slate-700">New status</label>
                    <select id="bulk_lead_status" name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" data-bulk-leads-cancel class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" data-bulk-leads-confirm class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Confirm</button>
                </div>
            </div>
        </dialog>
    </form>
    {{ $submissions->links() }}
</div>
@endsection

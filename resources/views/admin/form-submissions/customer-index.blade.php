@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <x-portal-notices :validation-errors="session('errors', $errors)" />
    <header><h1 class="text-2xl font-semibold">Leads</h1><p class="mt-2 text-sm text-slate-600">Enquiries for {{ $currentWebsite->name }}. Keep their status, tags and notes up to date.</p></header>
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">@foreach(\App\Models\FormSubmission::STATUS_LABELS as $key => $label)<div class="ui-panel ui-section"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-2 text-xl font-semibold">{{ $summary->get($key, 0) }}</dd></div>@endforeach</dl>
    <form method="GET" action="{{ route('admin.form-submissions.index') }}" class="ui-panel ui-section flex flex-wrap items-end gap-3">
        <div><label for="lead-search" class="ui-label">Search</label><input id="lead-search" name="search" value="{{ request('search') }}" class="ui-input mt-1"></div>
        <div><label for="lead-status" class="ui-label">Status</label><select id="lead-status" name="status" class="ui-input mt-1"><option value="">All statuses</option>@foreach(\App\Models\FormSubmission::STATUS_LABELS as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="lead-tag" class="ui-label">Tag</label><select id="lead-tag" name="tag_id" class="ui-input mt-1"><option value="">All tags</option>@foreach($leadTags as $tag)<option value="{{ $tag->id }}" @selected((string) request('tag_id') === (string) $tag->id)>{{ $tag->name }}</option>@endforeach</select></div>
        <button class="ui-button ui-button-secondary">Filter leads</button><a href="{{ route('admin.form-submissions.index', ['reset_filters' => 1]) }}" class="ui-button ui-button-secondary">Clear</a>
    </form>
    <section class="ui-panel ui-section divide-y divide-slate-950/10">@forelse($submissions as $lead)<a href="{{ route('admin.form-submissions.show', $lead) }}" class="block py-4 first:pt-0 last:pb-0"><p class="font-medium">{{ data_get($lead->data, 'name', data_get($lead->data, 'email', 'Website enquiry')) }}</p><p class="mt-1 text-sm text-slate-600">{{ $lead->resolvedStatusLabel() }} · {{ $lead->created_at->format('j M Y') }}</p><p class="mt-1 text-sm text-slate-500">{{ $lead->tags->pluck('name')->implode(', ') }}</p></a>@empty<p class="text-sm text-slate-500">No leads match your filters.</p>@endforelse</section>
    {{ $submissions->links() }}
</div>
@endsection

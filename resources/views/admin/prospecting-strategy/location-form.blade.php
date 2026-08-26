@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl space-y-6">
    <div><p class="text-sm font-medium text-teal-700">Prospecting strategy</p><h1 class="text-3xl font-semibold tracking-tight">{{ $location->exists ? 'Edit location' : 'Add location' }}</h1></div>
    <form method="POST" action="{{ $location->exists ? route('admin.prospecting-locations.update', $location) : route('admin.prospecting-locations.store') }}" class="grid gap-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @if ($location->exists) @method('PUT') @endif
        <label class="grid gap-1.5 text-sm font-medium">Location<input name="name" value="{{ old('name', $location->name) }}" required class="rounded-lg border border-slate-300 px-3 py-2.5"></label><label class="grid gap-1.5 text-sm font-medium">Slug<input name="slug" value="{{ old('slug', $location->slug) }}" class="rounded-lg border border-slate-300 px-3 py-2.5"></label><label class="grid gap-1.5 text-sm font-medium">Priority<input type="number" name="priority" value="{{ old('priority', $location->priority ?? 50) }}" min="0" max="1000" required class="rounded-lg border border-slate-300 px-3 py-2.5"></label><label class="flex items-center gap-3 text-sm font-medium"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $location->exists ? $location->enabled : true)) class="rounded border-slate-300">Enabled for automatic prospecting</label>
        @if ($errors->any())<div class="text-sm text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="flex gap-3"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Save location</button><a href="{{ route('admin.prospecting-industry-profiles.index') }}" class="px-4 py-2.5 text-sm font-semibold text-slate-600">Cancel</a></div>
    </form>
</div>
@endsection

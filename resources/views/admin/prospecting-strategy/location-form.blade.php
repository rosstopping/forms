@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl space-y-6">
    <div><p class="font-medium text-teal-700 text-base sm:text-sm">Prospecting strategy</p><h1 class="text-3xl font-semibold tracking-tight">{{ $location->exists ? 'Edit location' : 'Add location' }}</h1></div>
    <form method="POST" action="{{ $location->exists ? route('admin.prospecting-locations.update', $location) : route('admin.prospecting-locations.store') }}" class="ui-panel ui-section grid gap-5">
        @csrf @if ($location->exists) @method('PUT') @endif
        <label class="ui-label grid gap-1.5">Location<input name="name" value="{{ old('name', $location->name) }}" required class="ui-input"></label><label class="ui-label grid gap-1.5">Slug<input name="slug" value="{{ old('slug', $location->slug) }}" class="ui-input"></label><label class="ui-label grid gap-1.5">Priority<input type="number" name="priority" value="{{ old('priority', $location->priority ?? 50) }}" min="0" max="1000" required class="ui-input"></label><label class="ui-label flex items-center gap-3"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $location->exists ? $location->enabled : true))>Enabled for automatic prospecting</label>
        @if ($errors->any())<div class="text-sm text-rose-700">{{ $errors->first() }}</div>@endif
        <div class="flex gap-3"><button type="submit" class="ui-button ui-button-primary">Save location</button><a href="{{ route('admin.prospecting-industry-profiles.index') }}" class="px-4 py-2.5 text-sm font-semibold text-slate-600">Cancel</a></div>
    </form>
</div>
@endsection

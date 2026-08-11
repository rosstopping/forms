@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Choose a Search Console property</h1>
        <p class="mt-1 text-sm text-slate-600">Select the property used to guide content opportunities for {{ $website->name }}.</p>
    </div>
    <form method="POST" action="{{ route('admin.search-console.property.store', $website) }}" class="space-y-4 rounded-lg border bg-white p-5 shadow-sm">
        @csrf
        <div>
            <label for="property_url" class="block text-sm font-medium text-slate-700">Property</label>
            <select id="property_url" name="property_url" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @foreach ($properties as $property)
                    <option value="{{ $property['siteUrl'] }}">{{ $property['siteUrl'] }} ({{ $property['permissionLevel'] ?? 'unknown access' }})</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white">Save property</button>
            <a href="{{ route('admin.websites.show', $website) }}" class="rounded-md border px-3 py-2 text-sm font-medium text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection

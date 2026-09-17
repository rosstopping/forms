@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Choose a Search Console property</h1>
        <p class="mt-1 text-slate-600 text-base sm:text-sm">Select the property used to guide content opportunities for {{ $website->name }}.</p>
    </div>
    <form method="POST" action="{{ route('admin.search-console.property.store', $website) }}" class="ui-panel ui-section space-y-4">
        @csrf
        <div>
            <label for="property_url" class="ui-label block">Property</label>
            @if ($properties !== [])
                <select id="property_url" name="property_url" required class="ui-input mt-1 w-full">
                    @foreach ($properties as $property)
                        <option value="{{ $property['siteUrl'] }}">{{ $property['siteUrl'] }} ({{ $property['permissionLevel'] ?? 'unknown access' }})</option>
                    @endforeach
                </select>
            @else
                <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-base sm:text-sm">This Google account does not have a Search Console property matching this website’s configured domain.</p>
            @endif
            @error('property_url')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-2">
            @if ($properties !== [])
                <button type="submit" class="ui-button ui-button-primary">Save property</button>
            @endif
            <a href="{{ route('admin.websites.show', $website) }}" class="ui-button ui-button-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

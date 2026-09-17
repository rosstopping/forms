@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><h1 class="text-2xl font-semibold text-slate-950">Select a Business Profile</h1><p class="mt-1 text-slate-600 text-base sm:text-sm">Choose the location managed by {{ $website->name }}.</p></div>
    <div class="space-y-3">
        @forelse ($locations as $location)
            <form method="POST" action="{{ route('admin.business-profile.location.store', $website) }}" class="ui-panel flex items-center justify-between gap-4 p-4">
                @csrf
                <input type="hidden" name="account_name" value="{{ $location['accountName'] }}"><input type="hidden" name="location_name" value="{{ $location['name'] }}"><input type="hidden" name="location_title" value="{{ $location['title'] }}">
                <div><p class="font-medium text-slate-950">{{ $location['title'] }}</p><p class="text-slate-500 text-base sm:text-sm">{{ collect(data_get($location, 'storefrontAddress.addressLines', []))->join(', ') }}</p></div>
                <button type="submit" class="ui-button ui-button-primary">Select</button>
            </form>
        @empty
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 text-base sm:text-sm">This Google account has no available Business Profile locations.</p>
        @endforelse
    </div>
</div>
@endsection

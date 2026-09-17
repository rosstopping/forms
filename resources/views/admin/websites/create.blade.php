@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Add website</h1>
            <p class="text-slate-600 text-base sm:text-sm">Set up a website before it has submitted any forms.</p>
        </div>
        <a href="{{ route('admin.websites.index') }}" class="ui-button ui-button-secondary">Back to websites</a>
    </div>

    <div class="ui-panel ui-section">
        <form method="POST" action="{{ route('admin.websites.store') }}" class="space-y-5">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="ui-label block" for="name">Website name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="ui-input mt-1 w-full" placeholder="Acme Ltd">
                </div>

                <div>
                    <label class="ui-label block" for="domain">Primary domain</label>
                    <input id="domain" name="domain" type="text" value="{{ old('domain') }}" required class="ui-input mt-1 w-full" placeholder="example.com">
                    <p class="mt-1 text-slate-500 text-base sm:text-sm">A domain or full website URL can be entered.</p>
                </div>
            </div>

            <div>
                <label class="ui-label block" for="user_id">Initial manager</label>
                <select id="user_id" name="user_id" class="ui-input mt-1 w-full">
                    <option value="">Unassigned</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <label class="ui-label flex items-start gap-3 rounded-lg border border-slate-950/10 p-4">
                <input type="hidden" name="health_reports_enabled" value="0">
                <input type="checkbox" name="health_reports_enabled" value="1" class="mt-1" @checked(old('health_reports_enabled'))>
                <span>
                    <span class="block text-sm font-medium text-slate-900">Send weekly website health reports</span>
                    <span class="block text-xs text-slate-500">Reports will be sent to administrators and assigned website users.</span>
                </span>
            </label>

            <button type="submit" class="ui-button ui-button-primary">Create website</button>
        </form>
    </div>
</div>
@endsection

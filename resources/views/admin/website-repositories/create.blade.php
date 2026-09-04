@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-sm text-slate-500">GitHub integration</p>
        <h1 class="text-2xl font-semibold">Connect a repository</h1>
        <p class="mt-1 text-sm text-slate-600">Choose the repository that deploys {{ $website->name }}.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($unavailableInstallations->isNotEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">A GitHub connection needs reconnecting</p>
            <p class="mt-1">Sitewell could no longer access {{ $unavailableInstallations->join(', ', ' or ') }}. It has been removed from this list so you can continue using any other connected accounts.</p>
            <a href="{{ route('admin.github.connect', $website) }}" class="mt-3 inline-flex rounded-md bg-amber-900 px-3 py-2 font-medium text-white hover:bg-amber-800">Reconnect GitHub App</a>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.website-repositories.store', $website) }}" class="space-y-5 rounded-lg border bg-white p-5 shadow-sm">
        @csrf
        <div>
            @php
                $selectedRepositoryValue = old('repository');
                $selectedRepository = collect($repositories)->first(function (array $repository) use ($selectedRepositoryValue): bool {
                    return $selectedRepositoryValue === $repository['github_installation_id'].':'.$repository['id'];
                });
            @endphp
            <label for="repository-search" class="block text-sm font-medium text-slate-700">Repository</label>
            <div class="relative mt-1" data-searchable-select>
                <input
                    id="repository-search"
                    type="search"
                    value="{{ $selectedRepository ? $selectedRepository['full_name'].' · '.$selectedRepository['account_login'] : '' }}"
                    placeholder="Search repositories or accounts"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="repository-options"
                    aria-expanded="false"
                    data-searchable-select-input
                >
                <select id="repository" name="repository" class="sr-only" tabindex="-1" aria-hidden="true" data-searchable-select-native>
                <option value="">Select a repository</option>
                @foreach ($repositories as $repository)
                    @php($value = $repository['github_installation_id'].':'.$repository['id'])
                    <option value="{{ $value }}" @selected(old('repository') === $value)>{{ $repository['full_name'] }} · {{ $repository['account_login'] }}</option>
                @endforeach
                </select>
                <div id="repository-options" class="absolute z-20 mt-2 hidden max-h-72 w-full overflow-y-auto rounded-xl bg-white p-1 shadow-xl ring-1 ring-slate-950/10" role="listbox" data-searchable-select-options>
                    @forelse ($repositories as $repository)
                        @php($value = $repository['github_installation_id'].':'.$repository['id'])
                        <button type="button" class="flex w-full items-center justify-between gap-4 rounded-lg px-3 py-2.5 text-left hover:bg-teal-50 aria-selected:bg-teal-50" role="option" aria-selected="{{ $selectedRepositoryValue === $value ? 'true' : 'false' }}" data-searchable-select-option data-value="{{ $value }}" data-label="{{ $repository['full_name'] }} · {{ $repository['account_login'] }}">
                            <span class="min-w-0">
                                <span class="flex truncate text-sm font-medium text-slate-900">{{ $repository['full_name'] }}</span>
                                <span class="flex truncate text-xs text-slate-500">{{ $repository['account_login'] }}</span>
                            </span>
                            @if ($repository['private'] ?? false)
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">Private</span>
                            @endif
                        </button>
                    @empty
                        <p class="px-3 py-4 text-sm text-slate-500">No repositories are available.</p>
                    @endforelse
                    <p class="hidden px-3 py-4 text-sm text-slate-500" data-searchable-select-empty>No repositories match your search.</p>
                </div>
            </div>
            <p class="mt-2 text-sm text-slate-500">Start typing a repository or GitHub account name.</p>
            <p class="mt-2 hidden text-sm text-red-700" data-searchable-select-error>Please select a repository from the results.</p>
        </div>

        <div>
            <label for="project_path" class="block text-sm font-medium text-slate-700">Project path <span class="font-normal text-slate-500">(optional)</span></label>
            <input id="project_path" name="project_path" value="{{ old('project_path') }}" placeholder="apps/marketing-site" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Use this when the website lives inside a monorepository.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" @disabled($repositories->isEmpty()) class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">Connect repository</button>
            <a href="{{ route('admin.github.connect', $website) }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Install on another account</a>
            <a href="{{ route('admin.websites.show', $website) }}" class="px-2 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</a>
        </div>
    </form>
</div>
@endsection

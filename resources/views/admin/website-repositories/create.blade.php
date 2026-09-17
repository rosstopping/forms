@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <p class="text-slate-500 text-base sm:text-sm">GitHub integration</p>
        <h1 class="text-2xl font-semibold">Connect a repository</h1>
        <p class="mt-1 text-slate-600 text-base sm:text-sm">Choose the repository that deploys {{ $website->name }}.</p>
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
            <a href="{{ route('admin.github.connect', $website) }}" class="ui-button ui-button-secondary mt-3">Reconnect GitHub App</a>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.website-repositories.store', $website) }}" class="ui-panel ui-section space-y-5">
        @csrf
        <div>
            @php
                $selectedRepositoryValue = old('repository', $website->repository ? $website->repository->github_installation_id.':'.$website->repository->repository_id : null);
                $selectedRepository = collect($repositories)->first(function (array $repository) use ($selectedRepositoryValue): bool {
                    return $selectedRepositoryValue === $repository['github_installation_id'].':'.$repository['id'];
                });
            @endphp
            <label for="repository-search" class="ui-label block">Repository</label>
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
                    <option value="{{ $value }}" @selected($selectedRepositoryValue === $value)>{{ $repository['full_name'] }} · {{ $repository['account_login'] }}</option>
                @endforeach
                </select>
                <div id="repository-options" class="ui-panel absolute z-20 mt-2 hidden max-h-72 w-full overflow-y-auto p-1 ring-slate-950/10" role="listbox" data-searchable-select-options>
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
                        <p class="px-3 py-4 text-slate-500 text-base sm:text-sm">No repositories are available.</p>
                    @endforelse
                    <p class="hidden px-3 py-4 text-slate-500 text-base sm:text-sm" data-searchable-select-empty>No repositories match your search.</p>
                </div>
            </div>
            <p class="mt-2 text-slate-500 text-base sm:text-sm">Start typing a repository or GitHub account name.</p>
            <p class="mt-2 hidden text-red-700 text-base sm:text-sm" data-searchable-select-error>Please select a repository from the results.</p>
        </div>

        <div>
            <label for="project_path" class="ui-label block">Project path <span class="font-normal text-slate-500">(optional)</span></label>
            <input id="project_path" name="project_path" value="{{ old('project_path', $website->repository?->project_path) }}" placeholder="apps/marketing-site" class="ui-input mt-1 w-full">
            <p class="mt-1 text-slate-500 text-base sm:text-sm">The source folder Copilot edits. Leave blank for a project at the repository root. For GitHub Actions builds, do not enter the build output folder here.</p>
        </div>

        <fieldset class="space-y-4 rounded-lg border border-slate-950/10 p-4">
            <legend class="px-1 text-sm font-medium text-slate-700">WordPress deployment from GitHub Actions (optional)</legend>
            <p class="text-slate-600 text-base sm:text-sm">For Eleventy and other build tools, fill in both fields. Sitewell waits for a successful build on the publishing branch and sends the artifact contents to WordPress. Leave both blank to deploy committed static files from the project path.</p>
            <div>
                <label for="wordpress_workflow_path" class="ui-label block">Workflow file</label>
                <input id="wordpress_workflow_path" name="wordpress_workflow_path" value="{{ old('wordpress_workflow_path', $website->repository?->wordpress_workflow_path) }}" placeholder=".github/workflows/build-site.yml" class="ui-input mt-1 w-full">
            </div>
            <div>
                <label for="wordpress_artifact_name" class="ui-label block">Build artifact name</label>
                <input id="wordpress_artifact_name" name="wordpress_artifact_name" value="{{ old('wordpress_artifact_name', $website->repository?->wordpress_artifact_name) }}" placeholder="wordpress-site" class="ui-input mt-1 w-full">
                <p class="mt-1 text-slate-500 text-base sm:text-sm">Use the upload-artifact name from your workflow. Upload the contents of _site (or your output folder), with index.html at the artifact root.</p>
            </div>
            <p class="text-slate-500 text-base sm:text-sm">The GitHub App needs Actions read access and the Workflow run webhook event enabled. Re-run the workflow after saving these settings to publish your first build.</p>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" @disabled($repositories->isEmpty()) class="ui-button ui-button-primary disabled:cursor-not-allowed">Connect repository</button>
            <a href="{{ route('admin.github.connect', $website) }}" class="ui-button ui-button-secondary">Install on another account</a>
            <a href="{{ route('admin.websites.show', $website) }}" class="px-2 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</a>
        </div>
    </form>
</div>
@endsection

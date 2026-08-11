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

    <form method="POST" action="{{ route('admin.website-repositories.store', $website) }}" class="space-y-5 rounded-lg border bg-white p-5 shadow-sm">
        @csrf
        <div>
            <label for="repository" class="block text-sm font-medium text-slate-700">Repository</label>
            <select id="repository" name="repository" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                <option value="">Select a repository</option>
                @foreach ($repositories as $repository)
                    @php($value = $repository['github_installation_id'].':'.$repository['id'])
                    <option value="{{ $value }}" @selected(old('repository') === $value)>{{ $repository['full_name'] }} · {{ $repository['account_login'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="project_path" class="block text-sm font-medium text-slate-700">Project path <span class="font-normal text-slate-500">(optional)</span></label>
            <input id="project_path" name="project_path" value="{{ old('project_path') }}" placeholder="apps/marketing-site" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">Use this when the website lives inside a monorepository.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Connect repository</button>
            <a href="{{ route('admin.github.connect', $website) }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Install on another account</a>
            <a href="{{ route('admin.websites.show', $website) }}" class="px-2 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</a>
        </div>
    </form>
</div>
@endsection

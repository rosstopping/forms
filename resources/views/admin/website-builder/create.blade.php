@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-sm font-medium text-teal-700">New client setup</p>
        <h1 class="text-2xl font-semibold text-slate-950">Website builder</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">Create a static website, publish it to Netlify, connect its GitHub repository, and register its Sitewell contact form in one go.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.website-builder.store') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div><label for="name" class="block text-sm font-medium text-slate-700">Website name</label><input id="name" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Acme Studio"></div>
            <div><label for="sector" class="block text-sm font-medium text-slate-700">Sector</label><input id="sector" name="sector" value="{{ old('sector') }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Architecture"></div>
        </div>

        <div><label for="description" class="block text-sm font-medium text-slate-700">Business and website brief</label><textarea id="description" name="description" rows="5" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="What the business does, who it serves, and the main action visitors should take.">{{ old('description') }}</textarea></div>

        <div>
            <label for="pages" class="block text-sm font-medium text-slate-700">Pages</label>
            <textarea id="pages" name="pages" rows="4" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Home&#10;About&#10;Services&#10;Contact">{{ old('pages', "Home\nAbout\nServices\nContact") }}</textarea>
            <p class="mt-1 text-xs text-slate-500">Enter one page per line. Home and Contact are always included.</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div><label for="repository_name" class="block text-sm font-medium text-slate-700">GitHub repository name</label><input id="repository_name" name="repository_name" value="{{ old('repository_name') }}" required pattern="[a-z0-9._-]+" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="acme-studio"></div>
            <div>
                <label for="github_installation_id" class="block text-sm font-medium text-slate-700">GitHub account</label>
                <select id="github_installation_id" name="github_installation_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Select an account</option>@foreach ($installations as $installation)<option value="{{ $installation->id }}" @selected((string) old('github_installation_id') === (string) $installation->id)>{{ $installation->account_login }}{{ (($installation->permissions['administration'] ?? null) !== 'write' || ($installation->permissions['contents'] ?? null) !== 'write') ? ' — permission update required' : '' }}</option>@endforeach</select>
                @if ($installations->isEmpty())<p class="mt-1 text-xs text-amber-700">Install the Sitewell GitHub App for all repositories before using the builder.</p>@endif
                @if ($installations->contains(fn ($installation) => ($installation->permissions['administration'] ?? null) !== 'write' || ($installation->permissions['contents'] ?? null) !== 'write'))<p class="mt-1 text-xs text-amber-700">In the GitHub App settings, set Repository permissions → Administration and Contents to Read and write, then approve the updated permissions on the installation.</p>@endif
                <a href="{{ route('admin.website-builder.github.connect') }}" class="mt-2 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-900">Reconnect GitHub</a>
            </div>
        </div>

        <div>
            <label for="user_id" class="block text-sm font-medium text-slate-700">Sitewell owner</label>
            <select id="user_id" name="user_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="">Unassigned</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>@endforeach</select>
        </div>

        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">The build runs in the background, so you can close this page after submitting. GitHub, Netlify, the website design, and the Sitewell website record will be created by the queue worker.</div>
        <div class="flex flex-wrap items-center gap-3"><button type="submit" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Queue website build</button><a href="{{ route('admin.websites.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Cancel</a></div>
    </form>

    @if ($builds->isNotEmpty())
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4">
                <div><h2 class="font-semibold text-slate-950">Recent builds</h2><p class="text-sm text-slate-600">Refresh this page to see the latest progress.</p></div>
                <a href="{{ route('admin.website-builder.create') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">Refresh</a>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($builds as $build)
                    <div class="flex flex-col gap-3 px-6 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-medium text-slate-950">{{ $build->details['name'] ?? 'Website build' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Queued {{ $build->created_at->diffForHumans() }}</p>
                            @if ($build->error)<p class="mt-2 max-w-3xl text-sm text-red-700">{{ $build->error }}</p>@endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $build->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($build->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($build->status) }}</span>
                            @if ($build->website)<a href="{{ route('admin.websites.show', $build->website) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900">View website</a>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

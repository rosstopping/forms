@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="text-sm text-slate-600">A simple view of recent activity.</p>
        </div>

        <nav class="flex flex-wrap gap-2">
            <a href="{{ route('admin.dashboard') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Dashboard</a>
            <a href="{{ route('admin.websites.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Websites</a>
            <a href="{{ route('admin.forms.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Forms</a>
            <a href="{{ route('admin.form-submissions.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Submissions</a>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Logout</button>
            </form>
        </nav>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.websites.index') }}" class="rounded-lg border bg-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md">
            <div class="text-sm text-slate-500">Websites</div>
            <div class="text-2xl font-semibold">{{ $websiteCount }}</div>
        </a>
        <a href="{{ route('admin.forms.index') }}" class="rounded-lg border bg-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md">
            <div class="text-sm text-slate-500">Forms</div>
            <div class="text-2xl font-semibold">{{ $formCount }}</div>
        </a>
        <a href="{{ route('admin.form-submissions.index') }}" class="rounded-lg border bg-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow-md">
            <div class="text-sm text-slate-500">Submissions</div>
            <div class="text-2xl font-semibold">{{ $submissionCount }}</div>
        </a>
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Today</div>
            <div class="text-2xl font-semibold">{{ $submissionToday }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Recent websites</h2>
                <a href="{{ route('admin.websites.index') }}" class="text-sm text-slate-500 hover:text-slate-700">View all</a>
            </div>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($recentWebsites as $website)
                    <li class="flex items-center justify-between"><span>{{ e($website->name) }}</span><span class="text-slate-500">{{ $website->created_at->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Recent forms</h2>
                <a href="{{ route('admin.forms.index') }}" class="text-sm text-slate-500 hover:text-slate-700">View all</a>
            </div>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($recentForms as $form)
                    <li class="flex items-center justify-between"><span>{{ e($form->name) }}</span><span class="text-slate-500">{{ $form->created_at->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="rounded-lg border bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold">Recent submissions</h2>
            <a href="{{ route('admin.form-submissions.index') }}" class="text-sm text-slate-500 hover:text-slate-700">View all</a>
        </div>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($recentSubmissions as $submission)
                <li class="flex items-center justify-between"><span>{{ e($submission->source_domain ?: 'Unknown') }} / {{ e($submission->form?->name ?: 'Form') }}</span><span class="text-slate-500">{{ $submission->created_at->diffForHumans() }}</span></li>
            @endforeach
        </ul>
    </div>
</div>
@endsection

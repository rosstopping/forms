@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 to-slate-700 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-300">Operations overview</p>
                <h1 class="mt-2 text-2xl font-semibold">Welcome back</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-300">Keep an eye on new leads, website activity, and the forms that matter most.</p>
            </div>
            <a href="{{ route('admin.form-submissions.index') }}" class="inline-flex items-center rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-900 hover:bg-slate-100">View leads</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.websites.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-sm text-slate-500">Websites</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $websiteCount }}</div>
            <div class="mt-2 text-sm text-slate-500">Tracked properties</div>
        </a>
        <a href="{{ route('admin.forms.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-sm text-slate-500">Forms</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $formCount }}</div>
            <div class="mt-2 text-sm text-slate-500">Active collection points</div>
        </a>
        <a href="{{ route('admin.form-submissions.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-sm text-slate-500">Leads</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $submissionCount }}</div>
            <div class="mt-2 text-sm text-slate-500">New and evolving submissions</div>
        </a>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Today</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $submissionToday }}</div>
            <div class="mt-2 text-sm text-slate-500">Fresh activity</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Recent websites</h2>
                <a href="{{ route('admin.websites.index') }}" class="text-sm text-slate-500 hover:text-slate-700">View all</a>
            </div>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($recentWebsites as $website)
                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span class="font-medium text-slate-700">{{ e($website->name) }}</span><span class="text-slate-500">{{ $website->created_at->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Recent forms</h2>
                <a href="{{ route('admin.forms.index') }}" class="text-sm text-slate-500 hover:text-slate-700">View all</a>
            </div>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($recentForms as $form)
                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span class="font-medium text-slate-700">{{ e($form->name) }}</span><span class="text-slate-500">{{ $form->created_at->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

</div>
@endsection

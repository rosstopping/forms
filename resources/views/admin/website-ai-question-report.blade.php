@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <a href="{{ route('admin.websites.show', [$websiteAiQuestion->website, 'assistant' => 'open']) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← View website</a>
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3">
                <div><p class="font-medium uppercase tracking-wide text-amber-700 text-base sm:text-sm">Reported AI answer</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">WAI-{{ $websiteAiQuestion->id }}</h1></div>
                @if ($websiteAiQuestion->credited_at)
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Allowance returned</span>
                @else
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Needs review</span>
                @endif
            </div>
            <p class="text-slate-600 text-base sm:text-sm">{{ $websiteAiQuestion->website->name }} · {{ $websiteAiQuestion->user->name }} ({{ $websiteAiQuestion->user->email }}) · reported {{ $websiteAiQuestion->reported_at->diffForHumans() }}</p>
        </header>

        <section class="ui-panel ui-section space-y-5">
            <div><h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Question</h2><p class="mt-2 whitespace-pre-line leading-6 text-slate-900 text-base sm:text-sm">{{ $websiteAiQuestion->question }}</p></div>
            <div><h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assistant response</h2><p class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-4 leading-6 text-slate-700 text-base sm:text-sm">{{ $websiteAiQuestion->answer ?: $websiteAiQuestion->error }}</p></div>
            <div><h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">User’s report</h2><p class="mt-2 whitespace-pre-line leading-6 text-slate-700 text-base sm:text-sm">{{ $websiteAiQuestion->report_reason ?: 'No additional explanation was provided.' }}</p></div>
            @if ($websiteAiQuestion->failure_type)
                <div><h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failure diagnostic</h2><div class="mt-2 rounded-xl border border-red-200 bg-red-50 p-4"><p class="font-mono font-semibold text-red-900 text-base sm:text-sm">{{ $websiteAiQuestion->failure_type }}</p><p class="mt-2 whitespace-pre-line font-mono leading-5 text-red-800 text-base sm:text-sm">{{ $websiteAiQuestion->failure_detail }}</p></div></div>
            @endif
        </section>

        <section class="ui-panel ui-section">
            @if ($websiteAiQuestion->credited_at)
                <p class="font-medium text-emerald-800 text-base sm:text-sm">This request was returned to the user’s allowance {{ $websiteAiQuestion->credited_at->diffForHumans() }} by {{ $websiteAiQuestion->creditedBy?->name ?? 'an administrator' }}.</p>
            @else
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div><h2 class="font-semibold text-slate-950">Return this request?</h2><p class="mt-1 leading-6 text-slate-600 text-base sm:text-sm">It will no longer count towards this user’s weekly question limit.</p></div>
                    <form method="POST" action="{{ route('admin.website-ai-question-reports.credit', $websiteAiQuestion) }}">@csrf<button type="submit" class="ui-button ui-button-primary">Return to allowance</button></form>
                </div>
            @endif
        </section>
    </div>
@endsection

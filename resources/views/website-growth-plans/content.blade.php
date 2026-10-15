@php($report = $plan->report)
<main class="isolate mx-auto max-w-5xl space-y-12 px-5 py-8 sm:px-8 sm:py-14">
    <header class="border-b border-slate-950/10 pb-8">
        <p class="text-2xl font-semibold tracking-tight">sitewell.</p>
        @if($preview)<p class="mt-5 rounded-lg bg-amber-50 p-3 text-base text-amber-900 sm:text-sm">Staff preview · Generated report</p>@endif
        <p class="mt-8 text-base text-slate-500 sm:text-sm">{{ $report['website_name'] }} · {{ $report['domain'] }}</p>
        <h1 class="mt-3 max-w-[24ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl">Your website plan of action.</h1>
        <p class="mt-5 max-w-[56ch] whitespace-pre-line text-lg text-pretty text-slate-600">{{ $report['summary'] }}</p>
        <p class="mt-5 text-base text-slate-500 sm:text-sm">Baseline captured {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->format('j F Y') }}@if($plan->published_at) · Published {{ $plan->published_at->format('j F Y') }}@endif.</p>
    </header>
    @if($report['business'] ?? null)
        <section aria-labelledby="business-focus" class="space-y-5">
            <h2 id="business-focus" class="text-2xl font-semibold tracking-tight text-balance">The business focus</h2>
            <dl class="grid gap-5 sm:grid-cols-2">
                @foreach(['services' => 'Priority services', 'locations' => 'Locations served', 'audience' => 'Who we want to reach', 'difference' => 'What sets you apart'] as $field => $label)
                    @if(filled($report['business'][$field] ?? null))<div><dt class="font-medium">{{ $label }}</dt><dd class="mt-2 whitespace-pre-line text-base text-pretty text-slate-600">{{ $report['business'][$field] }}</dd></div>@endif
                @endforeach
            </dl>
        </section>
    @endif
    <section aria-labelledby="starting-point" class="space-y-5">
        <h2 id="starting-point" class="text-2xl font-semibold tracking-tight text-balance">Where we are starting</h2>
        @if($report['health'])
            <p class="max-w-[56ch] text-base text-pretty text-slate-600">The saved website audit from {{ \Illuminate\Support\Carbon::parse($report['health']['completed_at'])->format('j F Y') }} recorded {{ $report['health']['failed'] }} failed checks and {{ $report['health']['warnings'] }} warnings. These findings guide the initial review.</p>
            @if($report['health']['findings'])
                <details class="border-y border-slate-950/10 py-4"><summary class="cursor-pointer font-medium">Website findings to review</summary><ul role="list" class="mt-4 divide-y divide-slate-950/10">@foreach($report['health']['findings'] as $finding)<li class="py-3"><p class="font-medium">{{ $finding['label'] ?? $finding['title'] ?? 'Website finding' }}</p><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $finding['message'] ?? '' }}</p></li>@endforeach</ul></details>
            @endif
        @else
            <p class="text-base text-slate-600">No completed website audit was available when this plan was prepared. Confirming website health is an initial action.</p>
        @endif
        <p class="max-w-[56ch] text-base text-pretty text-slate-600">The rankings below are saved Google desktop search estimates for the stated market. They show the highest observed result for the website, which may differ from the page we intend to rank. Search Console average positions are a separate measurement.</p>
    </section>
    <section aria-labelledby="priority-keywords" class="space-y-5">
        <h2 id="priority-keywords" class="text-2xl font-semibold tracking-tight text-balance">What we want to rank for</h2>
        @if($report['keywords'] === [])<p class="text-base text-slate-600">No saved keyword evidence was available. The roadmap below explains how we will establish the search baseline.</p>@endif
        <div class="-mx-5 -my-2 overflow-x-auto sm:-mx-8"><div class="inline-block min-w-full px-5 py-2 align-middle sm:px-8">
            <table class="w-full text-left text-base sm:text-sm">
                <caption class="sr-only">Starting rankings and six-month planning targets</caption>
                <thead><tr class="border-b border-slate-950/10"><th scope="col" class="whitespace-nowrap py-3 pr-5 font-medium">Search term</th><th scope="col" class="whitespace-nowrap px-5 py-3 font-medium">Current position</th><th scope="col" class="whitespace-nowrap py-3 pl-5 font-medium">Six-month target</th></tr></thead>
                <tbody class="divide-y divide-slate-950/10">@foreach($report['keywords'] as $keyword)
                    <tr><th scope="row" class="py-4 pr-5 font-medium">{{ $keyword['term'] }}<p class="mt-1 font-normal text-slate-500">{{ ($keyword['suggested'] ?? false) ? 'Suggested keyword' : ucfirst($keyword['assignment_role']).' keyword' }}</p></th>
                        <td class="px-5 py-4 tabular-nums">{{ $keyword['baseline'] ? ($keyword['baseline']['status'] === 'ranked' ? '#'.$keyword['baseline']['position'] : 'Not in top 100') : 'No recent saved check' }}</td>
                        <td class="py-4 pl-5 tabular-nums">{{ filled($keyword['target_best'] ?? null) ? '#'.$keyword['target_best'].'–#'.$keyword['target_worst'] : 'Insufficient evidence' }}</td></tr>
                @endforeach</tbody>
            </table>
        </div></div>
        <p class="max-w-[56ch] text-base text-pretty text-slate-500 sm:text-sm">Six-month ranges are indicative planning targets, not predicted or guaranteed rankings. Results depend on competition, the starting point, completed work and changes in Google’s results.</p>
    </section>
    <section aria-labelledby="page-actions" class="space-y-6">
        <h2 id="page-actions" class="text-2xl font-semibold tracking-tight text-balance">How we will get there</h2>
        @foreach($report['keywords'] as $keyword)
            <article class="space-y-3 border-t border-slate-950/10 pt-5">
                <h3 class="text-lg font-semibold text-balance">{{ $keyword['term'] }}</h3>
                <p class="whitespace-pre-line text-base text-pretty text-slate-600">{{ $keyword['action'] }}</p>
                <dl class="space-y-3 break-words text-base sm:text-sm">
                    <div><dt class="font-medium">Intended page</dt><dd class="mt-1 text-slate-600">{{ $keyword['intended_url'] ?: 'Choose the most relevant existing page or agree a new page.' }}</dd></div>
                    @if($keyword['baseline'])<div><dt class="font-medium">Ranking evidence</dt><dd class="mt-1 text-slate-600">{{ \Illuminate\Support\Carbon::parse($keyword['baseline']['observed_at'])->format('j M Y') }} · Desktop · {{ $keyword['baseline']['market_label'] ?? 'Selected search market' }} · {{ $keyword['baseline']['language_code'] === 'en' ? 'English' : strtoupper($keyword['baseline']['language_code']) }}@if($keyword['baseline']['ranking_url'])<p class="mt-1">Observed page: {{ $keyword['baseline']['ranking_url'] }}</p>@endif</dd></div>@endif
                    @if(isset($keyword['search_console']))<div><dt class="font-medium">Saved Search Console sample</dt><dd class="mt-1 text-slate-600">{{ \Illuminate\Support\Carbon::parse($keyword['search_console']['month'])->format('F Y') }} · {{ number_format($keyword['search_console']['clicks']) }} clicks · {{ number_format($keyword['search_console']['impressions']) }} impressions · average position {{ number_format($keyword['search_console']['position'], 1) }}. This period average is separate from the current rank.</dd></div>@endif
                    @if(filled($keyword['target_reason'] ?? null))<div><dt class="font-medium">Target assumptions</dt><dd class="mt-1 whitespace-pre-line text-slate-600">{{ $keyword['target_reason'] }}</dd></div>@endif
                </dl>
            </article>
        @endforeach
    </section>
    <section aria-labelledby="six-month-roadmap" class="space-y-6">
        <h2 id="six-month-roadmap" class="text-2xl font-semibold tracking-tight text-balance">The six-month roadmap</h2>
        <ol role="list" class="divide-y divide-slate-950/10">@foreach($report['roadmap'] as $month => $action)<li class="grid gap-2 py-5 sm:grid-cols-[7rem_1fr]"><h3 class="font-semibold">Month {{ $month + 1 }}</h3><p class="whitespace-pre-line text-base text-pretty text-slate-600">{{ $action }}</p></li>@endforeach</ol>
    </section>
    <section aria-labelledby="success-title" class="space-y-5 border-t border-slate-950/10 pt-8">
        <h2 id="success-title" class="text-2xl font-semibold tracking-tight text-balance">What success looks like</h2>
        <p class="whitespace-pre-line text-base text-pretty text-slate-600">{{ $report['success_measure'] }}</p>
        <h3 class="text-lg font-semibold text-balance">What we need from you</h3><p class="whitespace-pre-line text-base text-pretty text-slate-600">{{ $report['dependencies'] }}</p>
        @if($report['competitors'])<details><summary class="cursor-pointer font-medium">Competitors we will keep in view</summary><ul role="list" class="mt-3 space-y-2 text-base text-slate-600 sm:text-sm">@foreach($report['competitors'] as $domain)<li>{{ $domain }}</li>@endforeach</ul></details>@endif
    </section>
    @if(filled($report['limitations'] ?? null))<section class="space-y-4 border-t border-slate-950/10 pt-8"><h2 class="text-2xl font-semibold tracking-tight text-balance">Assumptions and evidence</h2><p class="whitespace-pre-line text-base text-pretty text-slate-600">{{ $report['limitations'] }}</p></section>@endif
    <footer class="border-t border-slate-950/10 pt-6 text-base text-slate-500 sm:text-sm">Prepared by Sitewell. This report preserves the saved starting point; ongoing results are reviewed separately.</footer>
</main>

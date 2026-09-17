@extends('layouts.app')
@section('content')
<div class="space-y-6">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="font-mono text-teal-700 text-base sm:text-sm">{{ $website->name }}</p><h1 class="mt-1 text-3xl font-semibold text-slate-950">AI Visibility</h1><p class="mt-2 text-slate-600 text-base sm:text-sm">When customers ask AI for businesses like yours, are you being recommended?</p></div>
        @if ($canManage && $settings->enabled)
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.ai-visibility.sync-keywords', $website) }}">@csrf<button type="submit" class="ui-button ui-button-secondary">Re-sync target keywords</button></form>
                <form method="POST" action="{{ route('admin.ai-visibility.check', $website) }}">@csrf<button type="submit" class="ui-button ui-button-primary">Check now</button></form>
            </div>
        @endif
    </header>
    @if (session('status'))<div role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if (! $settings->enabled || $activePromptCount === 0)
    <section class="rounded-xl border border-teal-200 bg-teal-50 p-6">
        <h2 class="text-xl font-semibold text-slate-950">See whether AI recommends {{ $settings->brand_name }}</h2>
        <p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">We use your tracked keywords, saved business profile and search data to prepare customer questions. Turn on tracking to run your first checks and keep checking automatically. New tracking starts weekly. You can edit the questions at any time.</p>
        @if ($activePromptCount > 0)
            <p class="mt-4 font-medium text-teal-900 text-base sm:text-sm">{{ $activePromptCount }} saved questions are ready to resume.</p>
        @elseif ($suggestions)
            <p class="mt-4 font-medium text-teal-900 text-base sm:text-sm">{{ count($suggestions) }} questions ready to track</p>
            <ul class="mt-2 space-y-2 text-sm text-slate-700">@foreach (array_slice($suggestions, 0, 3) as $idea)<li>{{ $idea['prompt'] }}</li>@endforeach</ul>
            @if (count($suggestions) > 3)<details class="mt-3 text-sm"><summary class="cursor-pointer font-medium text-teal-700">See all questions</summary><ul class="mt-2 space-y-2 text-slate-700">@foreach (array_slice($suggestions, 3) as $idea)<li>{{ $idea['prompt'] }}</li>@endforeach</ul></details>@endif
        @else
            <p class="mt-4 text-slate-700 text-base sm:text-sm">We have your website details, but need a service to prepare useful questions. Everything else is optional.</p>
        @endif
        @if ($canManage)
        <form method="POST" action="{{ route('admin.ai-visibility.settings', $website) }}" class="mt-5 space-y-4">
            @csrf @method('PUT')<input type="hidden" name="enabled" value="1">
            @if (blank($settings->brand_name))<label class="ui-label block">Business name<input name="brand_name" required maxlength="150" value="{{ old('brand_name') }}" class="ui-input mt-2 block w-full max-w-lg"></label>@endif
            @if (! $suggestions && $activePromptCount === 0)<label class="ui-label block">What service should we track?<input name="services" required maxlength="180" value="{{ is_string(old('services')) ? old('services') : '' }}" placeholder="For example, website design in Doncaster" class="ui-input mt-2 block w-full max-w-lg"></label>@endif
            @if ($openaiAvailable)<button type="submit" class="ui-button ui-button-primary">Turn on AI tracking</button>
            @else<p class="text-amber-900 text-base sm:text-sm">AI checks are temporarily unavailable. Please contact support to enable tracking.</p>@endif
        </form>
        <a href="#settings" class="mt-4 inline-block text-sm font-medium text-teal-700" onclick="document.getElementById('settings').open = true">Review business details</a>
        @else<p class="mt-4 text-slate-600 text-base sm:text-sm">A website manager can turn on tracking.</p>@endif
    </section>
    @else
        <p class="text-teal-800 text-base sm:text-sm">Tracking is on · {{ $activePromptCount }} questions · {{ [7 => 'Weekly', 14 => 'Every 2 weeks', 28 => 'Every 4 weeks'][$settings->frequency_days] }}</p>
    @endif
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <label class="ui-label">Period<select name="range" class="ui-input ml-2">@foreach ($ranges as $key => $label)<option value="{{ $key }}" @selected((string) $range === (string) $key)>{{ $label }}</option>@endforeach</select></label>
        <button type="submit" class="ui-button ui-button-secondary">Apply</button>
        <p class="text-slate-500 text-base sm:text-sm">{{ $report['period']['start'] }}–{{ $report['period']['end'] }}</p>
    </form>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="AI visibility metrics">
        <div class="rounded-xl bg-slate-950 p-6 text-white"><h2 class="text-sm text-slate-300">AI Visibility</h2><p class="mt-3 text-4xl font-semibold tabular-nums">{{ $report['score'] === null ? '—' : $report['score'].'%' }}</p><p class="mt-2 text-teal-300 text-base sm:text-sm">@if ($report['change'] !== null){{ $report['change'] > 0 ? '+' : '' }}{{ $report['change'] }} percentage points vs previous period @else No comparable previous period @endif</p></div>
        <div class="ui-panel ui-section"><h2 class="text-sm text-slate-600">Appearances / completed checks</h2><p class="mt-3 text-3xl font-semibold tabular-nums text-slate-950">{{ $report['brand_appearances'] }} / {{ $report['completed_checks'] }}</p><p class="mt-2 text-slate-500 text-base sm:text-sm">Visible for {{ $report['prompts_visible'] }} distinct prompts</p></div>
        <div class="ui-panel ui-section"><h2 class="text-sm text-slate-600">Citation visibility</h2><p class="mt-3 text-3xl font-semibold tabular-nums text-slate-950">{{ $report['citation_rate'] === null ? '—' : $report['citation_rate'].'%' }}</p><p class="mt-2 text-slate-500 text-base sm:text-sm">Your domain cited in {{ $report['cited_checks'] }} checks</p></div>
        <div class="ui-panel ui-section"><h2 class="text-sm text-slate-600">Average list position</h2><p class="mt-3 text-3xl font-semibold tabular-nums text-slate-950">{{ $report['average_position'] ?? '—' }}</p><p class="mt-2 text-slate-500 text-base sm:text-sm">{{ $report['position_checks'] }} measurable lists · {{ $report['competitor_count'] }} competitors detected</p></div>
    </section>
    @if ($report['coverage_changed'] && $report['previous_completed_checks'] > 0)<p class="text-amber-800 text-base sm:text-sm">Previous visibility: {{ $report['previous_score'] }}%. Tracking coverage changed, so the difference is not presented as a like-for-like gain or loss.</p>@endif
    @if ($report['failed_checks'])<p class="text-amber-800 text-base sm:text-sm">{{ $report['failed_checks'] }} failed checks are excluded from these scores.</p>@endif
    @if ($trend)<x-progress-chart title="Visibility over time" description="Appearance percentage on each date with completed checks. Gaps have no observations; changing question coverage can affect the score." :points="$trend" value-key="score" format="percentage" />
    @else<div class="rounded-xl border border-dashed border-slate-950/10 p-8 text-center"><h2 class="font-semibold text-slate-950">Your first visibility baseline starts here</h2><p class="mt-2 text-slate-600 text-base sm:text-sm">Turn on AI tracking above. Your results will appear here as the first checks complete.</p></div>@endif
    <section id="prompts" class="ui-panel ui-section">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold text-slate-950">Tracked questions</h2><p class="mt-1 text-slate-500 text-base sm:text-sm">Customer questions prepared from your website data. Edit a question to adjust what we check.</p></div>@if ($canManage)<form method="POST" action="{{ route('admin.ai-visibility.suggestions', $website) }}">@csrf<button class="text-sm font-semibold text-teal-700 hover:text-teal-900">Suggest more questions</button></form>@endif</div>
        <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Prompt</th><th class="px-3">Latest result</th><th class="px-3">Last checked</th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse ($prompts as $prompt)
            @php($latestResults = $prompt->results->where('prompt_fingerprint', $prompt->fingerprint)->unique('provider'))
            <tr><td class="max-w-md py-4 pr-3"><a href="{{ route('admin.ai-visibility.show', [$website, $prompt]) }}" class="font-medium text-teal-700 hover:underline">{{ $prompt->prompt }}</a><p class="mt-1 text-slate-500 text-base sm:text-sm">{{ $prompt->active ? 'Tracking' : 'Disabled' }} · {{ ucfirst($prompt->priority) }} priority</p></td><td class="px-3 py-4">@forelse ($latestResults as $result)<p class="whitespace-nowrap leading-6 text-base sm:text-sm">{{ $result->status !== 'completed' ? ucfirst($result->status) : ($result->brand_mentioned ? 'Mentioned' : 'Not mentioned') }}@if ($result->status === 'completed'){{ $result->website_cited ? ' · Cited' : ' · Not cited' }}{{ $result->brand_position ? ' · #'.$result->brand_position : '' }}@endif</p>@empty<span class="text-slate-500">Awaiting check</span>@endforelse</td><td class="whitespace-nowrap px-3 text-xs text-slate-500">{{ $latestResults->whereNotNull('checked_at')->max('checked_at')?->diffForHumans() ?? '—' }}</td></tr>
        @empty<tr><td colspan="3" class="py-6 text-slate-500">Your prepared questions will be added when you turn on tracking.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $prompts->links() }}</div>
        @if ($canManage)<details class="mt-5 border-t border-slate-100 pt-4"><summary class="cursor-pointer text-sm font-semibold text-teal-700">Add your own question</summary><form method="POST" action="{{ route('admin.ai-visibility.store', $website) }}" class="mt-4 max-w-3xl space-y-4">@csrf @include('admin.ai-visibility.prompt-fields', ['values' => []])<button type="submit" class="ui-button ui-button-primary">Add prompt</button></form></details>@endif
    </section>
    @if ($canManage && session()->has('aiPromptSuggestions'))
    <section class="rounded-xl border border-teal-200 bg-teal-50 p-6"><h2 class="text-lg font-semibold text-slate-950">Review suggested prompts</h2><p class="mt-1 text-slate-600 text-base sm:text-sm">Based on tracked keywords, services and locations. Existing prompts are never overwritten.</p><div class="mt-4 space-y-4">@forelse (session('aiPromptSuggestions') as $idea)<details class="rounded-lg bg-white p-4"><summary class="cursor-pointer text-sm font-medium text-teal-800">{{ $idea['prompt'] }}</summary><form method="POST" action="{{ route('admin.ai-visibility.store', $website) }}" class="mt-4 space-y-4">@csrf @include('admin.ai-visibility.prompt-fields', ['values' => $idea])<button type="submit" class="ui-button ui-button-primary">Add reviewed prompt</button></form></details>@empty<p class="text-slate-600 text-base sm:text-sm">No new suggestions. Add services and locations below, or add tracked Google keywords in SEO Intelligence.</p>@endforelse</div></section>
    @endif
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="ui-panel ui-section"><h2 class="text-lg font-semibold text-slate-950">Competitors</h2><p class="mt-1 text-slate-500 text-base sm:text-sm">Businesses identified in explicit recommendation lists. Share means a percentage of completed responses, not market share.</p><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Business</th><th>Appearances</th><th>Response share</th></tr></thead><tbody class="divide-y divide-slate-100"><tr class="text-teal-800"><th class="py-3 font-medium">Your business</th><td>{{ $report['brand_appearances'] }}</td><td>{{ $report['score'] === null ? '—' : $report['score'].'%' }}</td></tr>@foreach (array_slice($report['competitors'], 0, 15) as $competitor)<tr><th class="py-3 pr-3 font-medium">{{ $competitor['name'] }}</th><td>{{ $competitor['appearances'] }}</td><td>{{ $competitor['visibility'] }}%</td></tr>@endforeach</tbody></table></div></section>
        <section class="ui-panel ui-section"><h2 class="text-lg font-semibold text-slate-950">Opportunities</h2><div class="mt-4 divide-y divide-slate-100">@forelse ($report['opportunities'] as $opportunity)<article class="py-4 first:pt-0"><a href="{{ $opportunity['url'] }}" class="text-sm font-semibold text-teal-700 hover:underline">{{ $opportunity['title'] }}</a><p class="mt-2 leading-6 text-slate-600 text-base sm:text-sm">{{ $opportunity['reason'] }}</p></article>@empty<p class="text-slate-500 text-base sm:text-sm">No evidence-backed opportunities in this period. These will appear as checks complete.</p>@endforelse</div></section>
    </div>
    @if ($report['notable_changes'])<section class="ui-panel ui-section"><h2 class="text-lg font-semibold text-slate-950">What changed</h2><ul class="mt-3 space-y-2 text-sm text-slate-600">@foreach ($report['notable_changes'] as $change)<li>{{ $change['summary'] }}</li>@endforeach</ul></section>@endif
    @if ($canManage)
    <details id="settings" class="ui-panel ui-section" @if ($errors->any()) open @endif><summary class="cursor-pointer text-lg font-semibold text-slate-950">Business details and tracking settings</summary><form method="POST" action="{{ route('admin.ai-visibility.settings', $website) }}" class="mt-5 max-w-3xl space-y-5">@csrf @method('PUT')
        <p class="text-slate-600 text-base sm:text-sm">We fill in what we can from your website and saved Google Business Profile. Your tracked keywords supply questions automatically. Only change these details if needed.</p>
        <input type="hidden" name="enabled" value="0"><label class="ui-label flex items-center gap-2"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled))>AI tracking enabled</label>
        <label class="ui-label block">Frequency<select name="frequency_days" class="ui-input mt-2 block">@foreach ([7 => 'Weekly', 14 => 'Every 2 weeks', 28 => 'Every 4 weeks'] as $days => $label)<option value="{{ $days }}" @selected(old('frequency_days', $settings->frequency_days) == $days)>{{ $label }}</option>@endforeach</select></label>
        <label class="ui-label block">Business name<input name="brand_name" required maxlength="150" value="{{ old('brand_name', $settings->brand_name) }}" class="ui-input mt-2 w-full"></label>
        @foreach (['aliases' => 'Other brand names', 'services' => 'Additional services or topics', 'locations' => 'Locations'] as $field => $label)<label class="ui-label block">{{ $label }} <span class="font-normal text-slate-500">(optional, one per line)</span><textarea name="{{ $field }}" rows="2" class="ui-input mt-2 w-full">{{ is_array(old($field, $settings->$field)) ? implode("\n", old($field, $settings->$field)) : old($field, $settings->$field) }}</textarea></label>@endforeach
        <p class="text-slate-500 text-base sm:text-sm">Website domains: {{ implode(', ', $identity['domains']) ?: 'Add a domain in website settings to measure citations.' }}</p>
        <button type="submit" class="ui-button ui-button-primary">Save tracking settings</button>
    </form></details>
    @endif
    <p class="leading-5 text-slate-500 text-base sm:text-sm">AI Visibility = completed checks mentioning your brand ÷ completed checks × 100. Citation visibility uses checks citing your domain. Failed checks and dates without checks are excluded. Results are sampled from OpenAI search-enabled API responses, not the consumer ChatGPT experience, and can vary. List positions are recorded only where an ordered recommendation list is measurable. Citation matching uses source URLs supplied by the provider; redirect URLs may hide the original domain.</p>
</div>
@endsection

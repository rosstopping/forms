@php
    $comparisonCompetitors = $trackedCompetitors->where('excluded', false);
@endphp
<section class="ui-panel overflow-hidden" aria-labelledby="target-keywords-title">
    <div class="border-b border-slate-950/10 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="target-keywords-title" class="text-lg font-semibold text-slate-950">Target keywords</h2>
                    <span class="rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800">{{ $targetKeywords->whereNull('archived_at')->count() }} / 20 active</span>
                </div>
                <p class="mt-1 max-w-2xl text-slate-600 text-base sm:text-sm">Track terms this website intends to rank for, including terms with no current visibility.@if ($targetKeywords->isNotEmpty()) Positions are desktop ranking checks for the selected market, separate from Search Console measurements.@endif</p>
                <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors']) }}" class="mt-2 inline-block text-sm font-medium text-teal-700 hover:underline">{{ $comparisonCompetitors->isEmpty() ? 'Add competitors to compare rankings' : 'Manage comparison competitors' }}</a>
                @if ($canManageWebsite)
                    <form method="POST" action="{{ route('admin.seo-target-keywords.check-all', $website) }}" class="mt-3">
                        @csrf
                        <button type="submit" @disabled($targetKeywords->whereNull('archived_at')->isEmpty()) class="ui-button ui-button-secondary disabled:cursor-not-allowed">Check all rankings</button>
                    </form>
                @endif
            </div>
            @if ($canManageWebsite)
                <div class="w-full min-w-0 lg:max-w-xl">
                    <form method="POST" action="{{ route('admin.seo-target-keywords.store', $website) }}" class="ui-well grid min-w-0 gap-4 p-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                        @csrf
                        <div class="min-w-0 sm:col-span-2"><label for="target-term" class="ui-label block">Search term</label><input id="target-term" name="term" value="{{ old('term') }}" required maxlength="255" placeholder="e.g. emergency plumber barnsley" class="ui-input w-full min-w-0"></div>
                        <div class="min-w-0"><label for="target-priority" class="ui-label block">Priority</label><select id="target-priority" name="priority" class="ui-input w-full"><option value="normal">Normal</option><option value="high" @selected(old('priority') === 'high')>High</option></select></div>
                        <div class="min-w-0"><label for="target-note" class="ui-label block">Business context <span class="text-slate-500">(optional)</span></label><input id="target-note" name="note" value="{{ old('note') }}" maxlength="1000" placeholder="Service, audience, location…" class="ui-input w-full min-w-0"></div>
                        <div class="min-w-0 sm:col-span-2">
<label for="target-assignment-new-url" class="ui-label block">Intended page URL (optional)</label>
<input id="target-assignment-new-url" name="intended_url" type="url" maxlength="700" value="{{ old('intended_url') }}" class="ui-input w-full" placeholder="https://your-site.com/landing-page">
<p class="mt-1 text-sm text-slate-500">The page you want to rank, including a planned page.</p>
</div>
<div><label for="target-assignment-new-role" class="ui-label block">Page assignment</label><select id="target-assignment-new-role" name="assignment_role" class="ui-input w-full">@foreach (['supporting' => 'Supporting keyword', 'primary' => 'Primary keyword'] as $value => $label)<option value="{{ $value }}" @selected(old('assignment_role', 'supporting') === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="target-assignment-new-intent" class="ui-label block">Search intent</label><select id="target-assignment-new-intent" name="search_intent" class="ui-input w-full"><option value="">Not specified</option>@foreach (['informational', 'commercial', 'transactional', 'navigational'] as $value)<option value="{{ $value }}" @selected(old('search_intent') === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
@error('intended_url')<p class="text-sm text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
@error('term')<p class="text-red-700 sm:col-span-2 text-base sm:text-sm">{{ $message }}</p>@enderror
                        <div class="sm:col-span-2"><button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">Add target</button></div>
                    </form>

                    <details class="ui-panel mt-3 p-3" @if ($errors->has('bulk_terms')) open @endif>
                        <summary class="cursor-pointer select-none text-sm font-semibold text-slate-700">Bulk add keywords</summary>
                        <form method="POST" action="{{ route('admin.seo-target-keywords.bulk-store', $website) }}" class="mt-3 grid min-w-0 gap-3 border-t border-slate-100 pt-3">
                            @csrf
                            <div class="min-w-0">
                                <label for="target-bulk-terms" class="ui-label block">Keywords</label>
                                <textarea id="target-bulk-terms" name="bulk_terms" rows="8" maxlength="6000" required class="ui-input mt-1 min-h-40 w-full min-w-0" placeholder="emergency plumber barnsley&#10;boiler installation barnsley&#10;boiler repair barnsley">{{ old('bulk_terms') }}</textarea>
                                <p class="mt-1 text-slate-500 text-base sm:text-sm">Enter one keyword per line, up to 20. Every keyword will use normal priority.</p>
                            </div>
                            @error('bulk_terms')<p class="text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                            <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto sm:justify-self-start">Add keywords</button>
                        </form>
                    </details>
                </div>
            @endif
        </div>
    </div>

    <div class="divide-y divide-slate-950/10">
        @forelse ($targetKeywords as $target)
            @php
                $latest = $target->rankings->first();
                $successful = $target->rankings->first(fn ($ranking) => in_array($ranking->status, ['ranked', 'not_found'], true));
                $previous = $successful ? $target->rankings->first(fn ($ranking) => $ranking->id !== $successful->id && in_array($ranking->status, ['ranked', 'not_found'], true)) : null;
                $currentPosition = $successful?->status === 'ranked' ? $successful->position : null;
                $previousPosition = $previous?->status === 'ranked' ? $previous->position : null;
            @endphp
            <article class="p-4 {{ $target->archived_at ? 'bg-slate-50' : '' }}">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-950">{{ $target->term }}</h3>
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $target->priority === 'high' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700' }}">{{ ucfirst($target->priority) }}</span>
                            @if ($target->archived_at)<span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-medium text-slate-700">Archived</span>@endif
                        </div>
                        @if ($target->note)<p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $target->note }}</p>@endif
                        <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <div><dt class="text-slate-500">Latest position</dt><dd class="font-semibold text-slate-900">{{ $successful ? ($currentPosition ? '#'.$currentPosition : 'Not in top 100') : 'Awaiting first check' }}</dd></div>
                            <div><dt class="text-slate-500">Movement</dt><dd class="font-medium text-slate-700">
                                @if (!$previous) —
                                @elseif ($currentPosition && !$previousPosition) Newly ranked
                                @elseif (!$currentPosition && $previousPosition) Dropped beyond top 100
                                @elseif ($currentPosition === $previousPosition) Unchanged
                                @elseif ($currentPosition < $previousPosition) Improved {{ $previousPosition - $currentPosition }}
                                @else Declined {{ $currentPosition - $previousPosition }}
                                @endif
                            </dd></div>
                            <div><dt class="text-slate-500">Checked</dt><dd class="text-slate-700">{{ $latest?->observed_at?->format('j M Y, H:i') ?? '—' }}</dd></div>
                        </dl>
                        @if ($latest?->status === 'failed')<p class="mt-2 text-red-700 text-base sm:text-sm">Latest check failed. The previous successful result remains shown.</p>@endif
                        @if ($target->intended_url)
                            <p class="mt-2 text-sm text-slate-700">{{ ucfirst($target->assignment_role) }} destination: <a href="{{ $target->intended_url }}" class="text-teal-700 underline" target="_blank" rel="noopener noreferrer">{{ $target->intended_url }}</a></p>
                            @php
                                $destinationReview = $keywordDestinationReviews[$target->id] ?? null;
                            @endphp
                            @if ($destinationReview)
                                <p class="mt-2 text-sm {{ $destinationReview['state'] === 'review' ? 'text-amber-800' : 'text-slate-600' }}">{{ $destinationReview['message'] }}</p>
                                @if ($destinationReview['pages'])
                                    <details class="mt-2"><summary class="cursor-pointer text-sm font-medium">Saved Search Console sample · {{ \Illuminate\Support\Carbon::parse($destinationReview['sample_at'])->format('j M Y') }}</summary>
                                        <p class="mt-2 text-sm text-slate-500">Sampled query/page rows for the 28-day reporting window. Positions are impression-weighted averages, not live ranks. Omitted and anonymised searches are unavailable.</p>
                                        <ul class="mt-2 space-y-2">@foreach ($destinationReview['pages'] as $page)<li class="break-words text-sm text-slate-600">{{ $page['intended'] ? 'Intended' : 'Other observed' }}: {{ $page['url'] }} · {{ number_format($page['clicks']) }} clicks · {{ number_format($page['impressions']) }} impressions · average position {{ $page['position'] !== null ? number_format($page['position'], 1) : 'unavailable' }}</li>@endforeach</ul>
                                    </details>
                                @endif
                            @endif
                        @endif
                        @if ($successful?->ranking_url)<span class="mt-2 block text-xs text-slate-500">Observed ranking page</span><a href="{{ $successful->ranking_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 block truncate text-sm text-teal-700 underline" title="{{ $successful->ranking_url }}">{{ $successful->ranking_url }}</a>@endif
                    </div>
                    @if ($canManageWebsite)
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <details class="ui-panel w-full p-3 open:shadow-sm lg:w-96">
                                <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">Edit target</summary>
                                <form method="POST" action="{{ route('admin.seo-target-keywords.update', [$website, $target]) }}" class="mt-4 grid min-w-0 gap-4 border-t border-slate-100 pt-4">
                                    @csrf @method('PUT')
                                    <div class="min-w-0"><label for="target-term-{{ $target->id }}" class="ui-label block">Search term</label><input id="target-term-{{ $target->id }}" name="term" value="{{ $target->term }}" required maxlength="255" class="ui-input w-full min-w-0"></div>
                                    <div class="max-w-40"><label for="target-priority-{{ $target->id }}" class="ui-label block">Priority</label><select id="target-priority-{{ $target->id }}" name="priority" class="ui-input w-full"><option value="normal" @selected($target->priority === 'normal')>Normal</option><option value="high" @selected($target->priority === 'high')>High</option></select></div>
                                    <div class="min-w-0 sm:col-span-2">
<label for="target-assignment-{{ $target->id }}-url" class="ui-label block">Intended page URL (optional)</label>
<input id="target-assignment-{{ $target->id }}-url" name="intended_url" type="url" maxlength="700" value="{{ $target->intended_url }}" class="ui-input w-full" placeholder="https://your-site.com/landing-page">
<p class="mt-1 text-sm text-slate-500">The page you want to rank, including a planned page.</p>
</div>
<div><label for="target-assignment-{{ $target->id }}-role" class="ui-label block">Page assignment</label><select id="target-assignment-{{ $target->id }}-role" name="assignment_role" class="ui-input w-full">@foreach (['supporting' => 'Supporting keyword', 'primary' => 'Primary keyword'] as $value => $label)<option value="{{ $value }}" @selected($target->assignment_role === $value)>{{ $label }}</option>@endforeach</select></div>
<div><label for="target-assignment-{{ $target->id }}-intent" class="ui-label block">Search intent</label><select id="target-assignment-{{ $target->id }}-intent" name="search_intent" class="ui-input w-full"><option value="">Not specified</option>@foreach (['informational', 'commercial', 'transactional', 'navigational'] as $value)<option value="{{ $value }}" @selected($target->search_intent === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
@error('intended_url')<p class="text-sm text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror
<div class="min-w-0"><label for="target-note-{{ $target->id }}" class="ui-label block">Business context</label><textarea id="target-note-{{ $target->id }}" name="note" rows="3" maxlength="1000" class="ui-input min-h-24 w-full min-w-0">{{ $target->note }}</textarea></div>
                                    <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto sm:justify-self-start">Save changes</button>
                                </form>
                            </details>
                            @if (!$target->archived_at)
                                <form method="POST" action="{{ route('admin.seo-target-keywords.check', [$website, $target]) }}">@csrf<button type="submit" class="ui-button ui-button-secondary">Check now</button></form>
                                <form method="POST" action="{{ route('admin.seo-target-keywords.archive', [$website, $target]) }}">@csrf @method('DELETE')<button type="submit" class="ui-button ui-button-secondary">Archive</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.seo-target-keywords.restore', [$website, $target]) }}">@csrf<button type="submit" class="ui-button ui-button-secondary">Restore</button></form>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($comparisonCompetitors->isNotEmpty())
                    <div class="mt-5 border-t border-slate-950/10 pt-4">
                        <h4 class="text-sm font-semibold text-slate-950">Competitor comparison</h4>
                        @if ($successful?->organic_results !== null)
                            <p class="mt-1 text-slate-500 text-base sm:text-sm">Same search results · {{ $successful->observed_at->format('j M Y, H:i') }} · {{ strtoupper($successful->language_code) }} · Location {{ $successful->location_code }} · {{ ucfirst($successful->device) }}{{ $successful->cached ? ' · Cached result' : '' }}. Lower positions rank higher.</p>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <caption class="sr-only">Ranking comparison for {{ $target->term }}</caption>
                                    <thead class="border-b border-slate-950/10 text-xs text-slate-500"><tr><th scope="col" class="px-3 py-2">Website</th><th scope="col" class="px-3 py-2">Position</th><th scope="col" class="px-3 py-2">Compared with you</th><th scope="col" class="px-3 py-2">Ranking page</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr class="bg-teal-50">
                                            <th scope="row" class="px-3 py-3 font-semibold text-teal-950">Your website</th>
                                            <td class="whitespace-nowrap px-3 py-3 font-semibold tabular-nums">{{ $currentPosition ? '#'.$currentPosition : 'Not in top 100' }}</td>
                                            <td class="px-3 py-3 text-slate-500">—</td>
                                            <td class="px-3 py-3">@if ($successful->ranking_url && in_array(parse_url($successful->ranking_url, PHP_URL_SCHEME), ['http', 'https'], true))<a href="{{ $successful->ranking_url }}" target="_blank" rel="noopener noreferrer" class="block max-w-xs truncate text-teal-700 underline" title="{{ $successful->ranking_url }}">{{ $successful->ranking_url }}</a>@else — @endif</td>
                                        </tr>
                                        @foreach ($comparisonCompetitors as $competitor)
                                            @php
                                                $competitorResult = collect($successful->organic_results)->firstWhere('domain', \App\Models\WebsiteDomain::canonicalDomain(strtolower($competitor->domain)));
                                                $competitorPosition = $competitorResult['position'] ?? null;
                                                $competitorUrl = $competitorResult['url'] ?? null;
                                            @endphp
                                            <tr>
                                                <th scope="row" class="break-all px-3 py-3 font-medium text-slate-900">{{ $competitor->domain }}</th>
                                                <td class="whitespace-nowrap px-3 py-3 font-semibold tabular-nums">{{ $competitorPosition ? '#'.$competitorPosition : 'Not in top 100' }}</td>
                                                <td class="whitespace-nowrap px-3 py-3">
                                                    @if ($competitorPosition && $currentPosition)
                                                        @if ($competitorPosition < $currentPosition)<span class="text-amber-800">{{ $currentPosition - $competitorPosition }} {{ Str::plural('place', $currentPosition - $competitorPosition) }} ahead of you</span>
                                                        @elseif ($competitorPosition > $currentPosition)<span class="text-teal-800">{{ $competitorPosition - $currentPosition }} {{ Str::plural('place', $competitorPosition - $currentPosition) }} behind you</span>
                                                        @else Same position
                                                        @endif
                                                    @elseif ($competitorPosition)<span class="text-amber-800">Ranks above you</span>
                                                    @elseif ($currentPosition)<span class="text-teal-800">You rank higher</span>
                                                    @else <span class="text-slate-500">Neither in top 100</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3">@if ($competitorUrl && in_array(parse_url($competitorUrl, PHP_URL_SCHEME), ['http', 'https'], true))<a href="{{ $competitorUrl }}" target="_blank" rel="noopener noreferrer" class="block max-w-xs truncate text-teal-700 underline" title="{{ $competitorUrl }}">{{ $competitorUrl }}</a>@else — @endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mt-2 text-slate-600 text-base sm:text-sm">Run a ranking check to compare your position with tracked competitors. Older checks do not contain competitor results.</p>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="p-8 text-center"><h3 class="font-semibold text-slate-950">No target keywords yet</h3><p class="mt-1 text-slate-600 text-base sm:text-sm">Add the terms the business wants to rank for. Adding a term does not start a paid check.</p></div>
        @endforelse
    </div>
</section>

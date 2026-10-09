<section class="ui-panel ui-section" aria-labelledby="content-schedule-title">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 id="content-schedule-title" class="text-lg font-semibold text-balance text-slate-950">Content schedule</h2>
            <p class="mt-2 max-w-2xl text-base text-pretty text-slate-500 sm:text-sm">Set a steady rhythm for your website. Sitewell starts with queued requests, then eligible competitor briefs and target keywords, and prepares every change for review.</p>
        </div>
        @if ($website->repository && Auth::user()?->isAdmin())
            <form method="POST" action="{{ route('admin.content-generations.store', $website) }}">
                @csrf
                <input type="hidden" name="content_section" value="activity">
                <button type="submit" class="ui-button ui-button-secondary ui-button-small">Generate now</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.content-plans.update', $website) }}" class="mt-6 space-y-6">
        @csrf @method('PUT')
        <input type="hidden" name="content_section" value="automation">
        <input type="hidden" name="enabled" value="0">
        <div class="rounded-xl bg-teal-50/70 p-4">
            <label for="content-schedule-enabled" class="ui-label flex items-center gap-3 bg-transparent text-teal-950 hover:bg-transparent">
                <input id="content-schedule-enabled" type="checkbox" name="enabled" value="1" @checked(old('enabled', $contentPlan?->enabled))>
                <span>Enable scheduled content improvements</span>
            </label>
            @if ($nextContentRun)
                <p class="mt-2 text-base text-teal-800 sm:text-sm">Next scheduled run: {{ $nextContentRun->copy()->setTimezone($contentPlan->timezone)->format('l j F, H:i') }} ({{ $contentPlan->timezone }}).</p>
            @elseif ($contentScheduleReason)
                <p class="mt-2 text-base text-teal-800 sm:text-sm">{{ $contentScheduleReason }}</p>
            @else
                <p class="mt-2 text-base text-teal-800 sm:text-sm">Turn this on when you are ready for Sitewell to work through your queue.</p>
            @endif
            @error('enabled')<p class="mt-2 text-base text-rose-700 sm:text-sm" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="ui-well p-4 sm:p-5">
            <label for="content-mode" class="ui-label block">Content strategy</label>
            <select id="content-mode" name="content_mode" class="ui-input mt-2 w-full">
                @foreach (['balanced' => 'Balanced — existing and new content', 'new_only' => 'New content only', 'existing_only' => 'Existing-page optimisation only'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('content_mode', $contentPlan?->content_mode ?? 'balanced') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-slate-500">Incompatible requests stay in the queue. Classify requests before preparation when using a restricted mode. Every draft requires publication approval.</p>
            @error('content_mode')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            <fieldset class="mt-5"><legend class="font-medium">Monthly content maximums</legend><p class="mt-2 text-sm text-slate-500">Content queue limits cover work prepared through Copilot, Pixel or manual tasks. Ceilings, never publishing targets. Blank means no additional limit; zero pauses that work. Copilot is measured in task starts, not currency. Reservations count preparation starts, not published pages. Failed attempts retain their reservation; retries of the same manual or Pixel request do not count twice.</p><div class="mt-3 grid gap-4 sm:grid-cols-3">
            @foreach (['monthly_article_limit' => 'New articles', 'monthly_optimisation_limit' => 'Existing-page optimisations', 'monthly_copilot_limit' => 'Copilot tasks'] as $field => $label)
                <div><label for="{{ $field }}" class="ui-label block">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="1000" value="{{ old($field, $contentPlan?->$field) }}" class="ui-input w-full">@error($field)<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
            @endforeach
            </div></fieldset>
            <p class="mt-3 text-sm text-slate-600">This month: {{ $contentBudgetUsage['articles'] }} article starts · {{ $contentBudgetUsage['optimisations'] }} optimisation starts · {{ $contentBudgetUsage['copilot'] }} Copilot task reservations. Uses the plan timezone.</p>
            <label for="article-path" class="ui-label mt-5 block">Preferred article section</label>
            <input id="article-path" name="article_path" value="{{ old('article_path', $contentPlan?->article_path) }}" placeholder="/guides/" maxlength="200" class="ui-input mt-2 w-full">
            <p class="mt-2 text-sm text-slate-500">Optional path for new articles, such as /guides/ or /blog/. Drafts still follow the existing site templates, links and sitemap. Publication always requires approval.</p>
            @error('article_path')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            <input type="hidden" name="keyword_research_enabled" value="0">
            <label for="content-keyword-research" class="ui-label mt-5 flex items-center gap-3"><input id="content-keyword-research" type="checkbox" name="keyword_research_enabled" value="1" @checked(old('keyword_research_enabled', $contentPlan?->keyword_research_enabled))><span>Discover related keyword opportunities weekly</span></label>
            <p class="mt-2 text-sm text-slate-500">Opt-in paid search research, independent of competitors: one request every seven days, rotating your target keywords, with up to 20 related terms and 10 new candidates. No Copilot calls. Requires active research eligibility. Candidates stay in Discovered until selected for planning and approved after a coverage and business relevance review.</p>
            @if ($contentPlan?->keyword_research)
                <p class="mt-2 text-sm text-slate-500">Last keyword discovery: {{ $contentPlan->keyword_research['status'] ?? 'unavailable' }}@if (isset($contentPlan->keyword_research['provider_cost_usd'])) · recorded provider cost ${{ number_format($contentPlan->keyword_research['provider_cost_usd'], 4) }} USD @endif</p>
                @if (! empty($contentPlan->keyword_research['error']))<p class="mt-2 text-sm text-amber-800">{{ $contentPlan->keyword_research['error'] }}</p>@endif
            @endif
            <input type="hidden" name="discovery_enabled" value="0">
            <label for="content-discovery" class="ui-label mt-5 flex items-center gap-3"><input id="content-discovery" type="checkbox" name="discovery_enabled" value="1" @checked(old('discovery_enabled', $contentPlan?->discovery_enabled))><span>Discover content opportunities daily from saved competitor research</span></label>
            <p class="mt-2 text-sm text-slate-500">Uses recent research in the selected market and your target keywords. No Copilot calls or extra research purchases. Candidates require relevance and coverage review before content preparation; no articles are created to fill a quota.</p>
            <input type="hidden" name="trend_research_enabled" value="0">
            <label for="content-trend-research" class="ui-label mt-5 flex items-center gap-3"><input id="content-trend-research" type="checkbox" name="trend_research_enabled" value="1" @checked(old('trend_research_enabled', $contentPlan?->trend_research_enabled))><span>Enrich opportunities with weekly trend research</span></label>
            <p class="mt-2 text-sm text-slate-500">Opt-in paid provider research: one request for up to five saved opportunity or target terms every seven days. No Copilot calls. Relative popularity supports prioritisation; it does not establish demand, business fit or a ranking guarantee. Requires active research eligibility.</p>
            @if ($contentPlan?->trend_research)
                <p class="mt-2 text-sm text-slate-500">Last trend research: {{ $contentPlan->trend_research['status'] ?? 'unavailable' }}@if (isset($contentPlan->trend_research['provider_cost_usd'])) · recorded provider cost ${{ number_format($contentPlan->trend_research['provider_cost_usd'], 4) }} USD @endif</p>
                @if (! empty($contentPlan->trend_research['error']))<p class="mt-2 text-sm text-amber-800">{{ $contentPlan->trend_research['error'] }}</p>@endif
            @endif
            <h3 class="mt-6 font-medium text-slate-900">When to prepare content</h3>
            <p class="mt-1 text-base text-slate-500 sm:text-sm">{{ $contentWeeklyLimit === 3 ? 'Your plan includes up to three scheduled runs each week.' : 'Your plan includes one scheduled run each week.' }} All selected days use the same time and timezone.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="weekday">Primary day</label>
                    <select id="weekday" name="weekday" class="ui-input w-full" @if ($errors->has('weekday')) aria-invalid="true" @endif>
                        @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $value => $day)
                            <option value="{{ $value }}" @selected((int) old('weekday', $contentPlan?->weekday ?? 1) === $value)>{{ $day }}</option>
                        @endforeach
                    </select>
                    @error('weekday')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="hour">Preparation time</label>
                    <select id="hour" name="hour" class="ui-input w-full" @if ($errors->has('hour')) aria-invalid="true" @endif>
                        @for ($hour = 0; $hour < 24; $hour++)
                            <option value="{{ $hour }}" @selected((int) old('hour', $contentPlan?->hour ?? 8) === $hour)>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</option>
                        @endfor
                    </select>
                    @error('hour')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label for="timezone">Timezone</label>
                    <input id="timezone" name="timezone" value="{{ old('timezone', $contentPlan?->timezone ?? 'Europe/London') }}" placeholder="Europe/London" required class="ui-input w-full" @if ($errors->has('timezone')) aria-invalid="true" @endif>
                    @error('timezone')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
            </div>
            @if ($contentWeeklyLimit === 3)
                <fieldset class="mt-5">
                    <legend class="text-base font-medium text-slate-800 sm:text-sm">Extra days</legend>
                    <p class="mt-1 text-base text-slate-500 sm:text-sm">Choose up to two, different from your primary day.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                        @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $value => $day)
                            <label for="content-extra-day-{{ $value }}" class="ui-label flex items-center gap-2 rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200/80 hover:bg-teal-50 has-checked:bg-teal-50 has-checked:text-teal-900 has-checked:ring-teal-600/30">
                                <input id="content-extra-day-{{ $value }}" type="checkbox" name="additional_weekdays[]" value="{{ $value }}" @checked(in_array($value, old('additional_weekdays', session()->hasOldInput() ? [] : ($contentPlan?->additional_weekdays ?? []))))>
                                <span>{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('additional_weekdays')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                    @foreach ($errors->get('additional_weekdays.*') as $messages)
                        @foreach ($messages as $message)<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@endforeach
                    @endforeach
                </fieldset>
            @elseif ($contentPlan?->additional_weekdays)
                <p class="mt-4 text-base text-amber-800 sm:text-sm">Your saved extra days are paused. They resume when this website has an active Complete subscription.</p>
            @endif
        </div>

        <div class="ui-well p-4 sm:p-5">
            <h3 class="font-medium text-slate-900">Competitor research</h3>
            <p class="mt-2 text-base text-slate-500 sm:text-sm">Compare competitors’ ranking pages with your own content to find useful improvements. {{ $contentWeeklyLimit === 3 ? 'Complete researches up to five tracked competitors every seven days, analysing up to eight pages per competitor.' : 'Growth researches up to three tracked competitors every fourteen days, analysing up to five pages per competitor.' }} Competitors with the oldest research are checked first.</p>
            <div class="mt-4">
                <label for="competitor-research-mode">How should Sitewell use competitor research?</label>
                <select id="competitor-research-mode" name="competitor_research_mode" class="ui-input w-full" aria-describedby="competitor-research-help" @if ($errors->has('competitor_research_mode')) aria-invalid="true" @endif>
                    @foreach (['manual' => 'Manual — research when requested', 'research' => 'Research only — find opportunities automatically', 'drafts' => 'Prepare drafts — use research in scheduled content'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('competitor_research_mode', $contentPlan?->competitor_research_mode ?? 'manual') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('competitor_research_mode')<p class="mt-2 text-base text-rose-700 sm:text-sm" role="alert">{{ $message }}</p>@enderror
                <p id="competitor-research-help" class="mt-2 text-base text-slate-500 sm:text-sm">Prepare drafts is recommended. Enable scheduled content above to prepare drafts within your existing weekly allowance. Research only can run while scheduled content is off. All drafts require review before publication.</p>
            </div>
            <p class="mt-3 text-base text-slate-500 sm:text-sm">Automatic research starts after you select Research only or Prepare drafts and save. It requires an active Growth or Complete subscription, a website domain and selected competitors. Excluded competitors are skipped.</p>
            @if ($contentPlan?->competitor_researched_at)
                <p class="mt-3 text-base text-slate-500 sm:text-sm">Last research batch scheduled: {{ $contentPlan->competitor_researched_at->copy()->setTimezone($contentPlan->timezone)->format('j M Y, H:i') }}. Check individual audits for results or failures.</p>
            @endif
            <a href="{{ route('admin.websites.section', [$website, 'section' => 'seo', 'seo_section' => 'competitors']) }}" class="mt-3 inline-block text-sm font-medium text-teal-700 hover:underline">Manage competitors and view research</a>
        </div>

        <details class="ui-well p-4 sm:p-5" @if ($errors->has('audience') || $errors->has('guidance')) open @endif>
            <summary class="cursor-pointer font-medium text-slate-800">Audience and editorial guidance</summary>
            <p class="mt-2 text-base text-slate-500 sm:text-sm">Give every draft a consistent voice. Tell us who you want to reach and what matters to your business.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="audience">Who are you writing for?</label>
                    <textarea id="audience" name="audience" rows="5" maxlength="20000" placeholder="For example: Local homeowners comparing reliable, practical options for their next renovation." class="ui-input w-full" @if ($errors->has('audience')) aria-invalid="true" @endif>{{ old('audience', $contentPlan?->audience) }}</textarea>
                    @error('audience')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="guidance">How should your content sound?</label>
                    <textarea id="guidance" name="guidance" rows="5" maxlength="20000" placeholder="For example: Friendly and informative. Use British English, explain unfamiliar terms, and avoid unsupported claims." class="ui-input w-full" @if ($errors->has('guidance')) aria-invalid="true" @endif>{{ old('guidance', $contentPlan?->guidance) }}</textarea>
                    @error('guidance')<p class="mt-2 text-rose-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="mt-3 text-slate-400 text-base sm:text-sm">Up to 20,000 characters in each field.</p>
        </details>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-xl text-base text-slate-500 sm:text-sm">Runs with no useful work are skipped. Automatic targets rest for at least 14 days between improvements.</p>
            <button type="submit" class="ui-button ui-button-primary">Save content plan</button>
        </div>
    </form>
</section>

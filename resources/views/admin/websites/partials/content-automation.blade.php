<section class="ui-panel ui-section" aria-labelledby="content-schedule-title">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 id="content-schedule-title" class="text-lg font-semibold text-balance text-slate-950">Content schedule</h2>
            <p class="mt-2 max-w-2xl text-base text-pretty text-slate-500 sm:text-sm">Set a steady rhythm for your website. Sitewell starts with queued requests, then eligible target keywords, and prepares every change for review.</p>
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
            <h3 class="font-medium text-slate-900">When to prepare content</h3>
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

<div id="website-panel-business-profile" class="space-y-6" role="region" aria-labelledby="website-tab-business-profile" data-tab-panel="business-profile" @if ($currentWebsiteSection !== 'business-profile') hidden @endif>
    @php
        $profile = $website->businessProfileConnection;
        $profileButton = 'ui-button ui-button-secondary ui-button-small';
        $profileInput = 'ui-input w-full';
    @endphp
    @if (! $profile)
        <section class="ui-panel ui-section">
            <h2 class="text-lg font-semibold text-balance text-slate-950">Your local presence, in one place</h2>
            <p class="mt-2 max-w-2xl text-base text-pretty text-slate-600 sm:text-sm">Connect Google Business Profile to plan posts, prepare review replies automatically, and keep your profile up to date. You approve every post, reply, and profile change before it goes live.</p>
            @if ($canManageWebsite)
                <div class="mt-4 text-sm"><a href="{{ route('admin.business-profile.connect', $website) }}" class="ui-button ui-button-primary">Connect Google Business Profile</a></div>
            @endif
        </section>
    @elseif (blank($profile->location_name))
        <section class="rounded-lg border border-amber-950/15 bg-amber-50 p-6" aria-labelledby="business-profile-location-required">
            <h2 id="business-profile-location-required" class="text-lg font-semibold text-balance text-amber-950">Choose the Google location to manage</h2>
            <p class="mt-2 max-w-2xl text-base text-pretty text-amber-900 sm:text-sm">Google is authorised. Select a location to start planning posts, preparing replies, and checking your profile.</p>
            @if ($canManageWebsite)
                <div class="mt-4 flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('admin.business-profile.locations', $website) }}" class="ui-button ui-button-primary">Select a location</a>
                    <a href="{{ route('admin.business-profile.connect', $website) }}" class="{{ $profileButton }}">Reconnect Google</a>
                </div>
            @endif
        </section>
    @else
        @php
            $latestAudit = $profile->audits->first();
            $postReadyCount = $businessPostCounts->get('pending_approval', 0);
            $replyReadyCount = $businessReviewCounts->get('pending_approval', 0);
            $reviewRetryCount = $businessReviewCounts->get('unanswered', 0) + $businessReviewCounts->get('failed', 0);
        @endphp
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-base text-slate-500 sm:text-sm">Google Business Profile</p>
                <h2 class="mt-1 text-xl font-semibold text-balance text-slate-950">{{ $profile->location_title ?: $website->name }}</h2>
                <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">Plan your posts. Stay on top of reviews. Approve what goes live.</p>
            </div>
            @if ($canManageWebsite)
                <details class="relative text-sm">
                    <summary class="{{ $profileButton }} cursor-pointer">Connection settings</summary>
                    <div class="ui-panel absolute right-0 z-10 mt-2 flex w-52 flex-col gap-2 p-3">
                        <a href="{{ route('admin.business-profile.locations', $website) }}" class="rounded-md px-2 py-1.5 hover:bg-slate-50">Change location</a>
                        <a href="{{ route('admin.business-profile.connect', $website) }}" class="rounded-md px-2 py-1.5 hover:bg-slate-50">Reconnect Google</a>
                    </div>
                </details>
            @endif
        </header>
        <div class="@container border-y border-slate-950/10 py-4">
            <dl class="grid grid-cols-2 gap-5 @2xl:grid-cols-4">
                @foreach ([['Topics queued', $businessPostCounts->get('queued', 0)], ['Posts to approve', $postReadyCount], ['Replies to approve', $replyReadyCount], ['Replies published', $businessReviewCounts->get('replied', 0)]] as [$label, $count])
                    <div class="min-w-0"><dt class="truncate text-base font-medium text-slate-600 sm:text-sm">{{ $label }}</dt><dd class="mt-1 text-2xl font-semibold text-slate-950 tabular-nums">{{ $count }}</dd></div>
                @endforeach
            </dl>
        </div>

        <section class="ui-panel overflow-hidden" aria-labelledby="business-posts-heading">
            <div class="border-b border-slate-950/10 p-5">
                <h3 id="business-posts-heading" class="text-lg font-semibold text-balance text-slate-950">Posts and automation</h3>
                <p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Choose an idea → add it to your queue → review the draft → publish to Google.</p>
            </div>
            <div class="grid lg:grid-cols-3">
                <div class="min-w-0 space-y-6 p-5 lg:col-span-2">
                    <div>
                        <h4 class="font-semibold text-slate-950">Suggested posts</h4>
                        <p class="mt-1 text-base text-pretty text-slate-500 sm:text-sm">Starting points from your connected profile and website. Add your own news or offer below.</p>
                        <div class="mt-4 divide-y divide-slate-950/10">
                            @foreach ($businessPostSuggestions as $key => $suggestion)
                                <article class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0 flex-1"><h5 class="font-medium text-slate-900">{{ $suggestion['title'] }}</h5><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">{{ $suggestion['description'] }}</p></div>
                                    @if ($canManageWebsite)
                                        <form method="POST" action="{{ route('admin.business-profile.posts.store', $website) }}" class="shrink-0">@csrf<input type="hidden" name="suggestion" value="{{ $key }}"><button type="submit" @disabled(in_array($suggestion['topic'], $businessQueuedTopics, true)) class="{{ $profileButton }} disabled:cursor-default disabled:opacity-50">{{ in_array($suggestion['topic'], $businessQueuedTopics, true) ? 'Already in queue' : 'Add to queue' }}</button></form>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                    @if ($canManageWebsite)
                        <form method="POST" action="{{ route('admin.business-profile.posts.store', $website) }}" class="ui-well p-4">
                            @csrf
                            <label for="business-post-topic" class="ui-label">Add your own post idea</label>
                            <p id="business-post-topic-help" class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Tell us what is new, which service to feature, or the exact details and dates of an offer. These facts will shape the draft.</p>
                            <textarea id="business-post-topic" name="topic" rows="3" required maxlength="1000" aria-describedby="business-post-topic-help" placeholder="For example: Introduce our new Saturday appointments, available from 10 October, 9am–1pm. Book through our website." class="{{ $profileInput }} mt-3">{{ old('topic') }}</textarea>
                            <button type="submit" class="{{ $profileButton }} mt-3">Add idea to queue</button>
                        </form>
                    @endif
                    @include('admin.websites.partials.business-profile-posts')
                </div>
                <aside class="min-w-0 border-t border-slate-950/10 bg-slate-50 p-5 lg:border-t-0 lg:border-l" aria-labelledby="business-automation-heading">
                    <h4 id="business-automation-heading" class="font-semibold text-slate-950">Your automation</h4>
                    <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">One routine for posts, replies, and profile health. Nothing is published automatically.</p>
                    <div class="mt-4 rounded-md border border-emerald-950/10 bg-emerald-50 p-3 text-base text-emerald-900 sm:text-sm">
                        <p class="font-medium">Automatic review drafts are on</p>
                        <p class="mt-1 text-pretty">We check for reviews hourly and draft replies to unanswered reviews of every rating. You edit and approve each reply.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.business-profile.update', $website) }}" class="mt-5">
                        @csrf @method('PUT')
                        <fieldset @disabled(! $canManageWebsite) class="space-y-5 disabled:opacity-70">
                            <div>
                                <input type="hidden" name="weekly_posts_enabled" value="0">
                                <label for="business-weekly-posts" class="ui-label flex items-center gap-2"><input id="business-weekly-posts" type="checkbox" name="weekly_posts_enabled" value="1" @checked(old('weekly_posts_enabled', $profile->weekly_posts_enabled)) class="size-4 accent-slate-900">Draft one queued post each week</label>
                                <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">Takes the oldest queued idea. An empty queue pauses drafting until you add more ideas.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><label for="business-post-day" class="ui-label">Draft day</label><select id="business-post-day" name="post_weekday" class="{{ $profileInput }} mt-1">@foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $value => $day)<option value="{{ $value }}" @selected((int) old('post_weekday', $profile->post_weekday) === $value)>{{ $day }}</option>@endforeach</select></div>
                                <div><label for="business-post-hour" class="ui-label">Draft time</label><select id="business-post-hour" name="post_hour" class="{{ $profileInput }} mt-1">@for ($hour = 0; $hour < 24; $hour++)<option value="{{ $hour }}" @selected((int) old('post_hour', $profile->post_hour) === $hour)>{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}:00</option>@endfor</select></div>
                            </div>
                            <div><label for="business-timezone" class="ui-label">Timezone</label><input id="business-timezone" name="timezone" required value="{{ old('timezone', $profile->timezone) }}" class="{{ $profileInput }} mt-1"></div>
                            <div>
                                <input type="hidden" name="weekly_audits_enabled" value="0">
                                <label for="business-weekly-audits" class="ui-label flex items-center gap-2"><input id="business-weekly-audits" type="checkbox" name="weekly_audits_enabled" value="1" @checked(old('weekly_audits_enabled', $profile->weekly_audits_enabled)) class="size-4 accent-slate-900">Check profile health weekly</label>
                            </div>
                            <div><label for="business-brand-guidance" class="ui-label">Voice and guidance</label><p id="business-brand-help" class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Used for both posts and review replies. Include your tone, useful business facts, and anything to avoid.</p><textarea id="business-brand-guidance" name="brand_guidance" rows="5" maxlength="20000" aria-describedby="business-brand-help" placeholder="Friendly and straightforward. Sign off review replies as ‘The team’. Avoid sales language when responding to complaints." class="{{ $profileInput }} mt-2">{{ old('brand_guidance', $profile->brand_guidance) }}</textarea></div>
                            @if ($canManageWebsite)<button type="submit" class="ui-button ui-button-primary">Save automation</button>@endif
                        </fieldset>
                    </form>
                </aside>
            </div>
        </section>

        @include('admin.websites.partials.business-profile-reviews')

        <details class="ui-panel ui-section">
            <summary class="cursor-pointer font-semibold text-slate-950">Profile health <span class="font-normal text-slate-500">· {{ $latestAudit ? ucfirst($latestAudit->status) : 'Not checked yet' }}</span></summary>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-base text-slate-600 sm:text-sm">{{ $latestAudit?->completed_at ? 'Last checked '.$latestAudit->completed_at->diffForHumans().'.' : 'Check for missing information and suggested profile improvements.' }}</p>@if ($canManageWebsite)<form method="POST" action="{{ route('admin.business-profile.audits.store', $website) }}">@csrf<button type="submit" class="{{ $profileButton }}">Run health check</button></form>@endif</div>
            @if ($latestAudit?->status === 'failed')<p class="mt-3 text-base text-rose-700 sm:text-sm">The last health check failed. Try again or reconnect Google.</p>@endif
            <div class="mt-4 divide-y divide-slate-950/10">
                @forelse ($latestAudit?->recommendations ?? [] as $recommendation)
                    <article class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1"><h4 class="font-medium text-slate-900">{{ $recommendation->title }}</h4><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">{{ $recommendation->description }}</p><p class="mt-1 capitalize text-slate-500 text-base sm:text-sm">{{ str_replace('_', ' ', $recommendation->status) }}</p></div>
                        @if ($canManageWebsite && $recommendation->status === 'pending')
                            <div class="flex flex-wrap gap-2">
                                @if ($recommendation->field_mask && $recommendation->proposed_value)<form method="POST" action="{{ route('admin.business-profile.recommendations.update', [$website, $recommendation]) }}">@csrf @method('PUT')<button type="submit" class="{{ $profileButton }}">Approve & apply</button></form>@endif
                                <form method="POST" action="{{ route('admin.business-profile.recommendations.destroy', [$website, $recommendation]) }}">@csrf @method('DELETE')<button type="submit" class="{{ $profileButton }}">Dismiss</button></form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="text-base text-slate-500 sm:text-sm">{{ $latestAudit?->status === 'completed' ? 'No changes recommended.' : 'Recommendations will appear after a health check completes.' }}</p>
                @endforelse
            </div>
        </details>
    @endif
</div>

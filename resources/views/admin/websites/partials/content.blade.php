@php
    $contentPlan = $website->contentPlan;
    $contentSections = ['plan' => 'Plan', 'queue' => 'Queue', 'activity' => 'Activity'];
    if ($canManageWebsite) {
        $contentSections['automation'] = 'Automation';
    }
    if ($canUseSdk) {
        $contentSections['sdk'] = 'SDK test';
    }
    $contentSections['connections'] = 'Connections';
    $requestedContentSection = request('content_section', 'queue');
    $currentContentSection = is_string($requestedContentSection) && array_key_exists($requestedContentSection, $contentSections) ? $requestedContentSection : 'queue';
    if ($errors->has('instructions')) {
        $currentContentSection = 'queue';
    } elseif ($canManageWebsite && collect(['trend_research_enabled', 'content_mode', 'monthly_article_limit', 'monthly_optimisation_limit', 'monthly_copilot_limit', 'discovery_enabled', 'enabled', 'weekday', 'hour', 'timezone', 'audience', 'guidance', 'additional_weekdays', 'additional_weekdays.*'])->contains(fn ($field) => $errors->has($field))) {
        $currentContentSection = 'automation';
    }
    $canSubmitContentRequest = $website->repository || (config('forms.pixel_ui_enabled') && $website->pixel_enabled);
@endphp
<div id="website-panel-content" class="space-y-6" role="region" aria-labelledby="website-tab-content" data-tab-panel="content" @if ($currentWebsiteSection !== 'content') hidden @endif>
    @if (! $canUseGrowthFeatures)
        <x-feature-upgrade-banner tier="Growth" title="Plan and request new content" description="Upgrade to Growth to submit content requests, plan improvements, and prepare reviewable website changes." />
        @include('admin.websites.partials.content-preview')
    @elseif (! $hasContentDeliveryConnection)
        <div class="space-y-5">
            @include('admin.websites.partials.content-connections')
        </div>
    @else
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0"><h2 class="text-xl font-semibold text-balance text-slate-950">Content workspace</h2><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Plan the work, follow its progress, and review changes before they go live.</p></div>
            <div class="text-sm"><a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact']) }}" class="ui-button ui-button-secondary ui-button-small">Review SEO impact →</a></div>
        </header>
        <div class="@container border-y border-slate-950/10 py-4">
            <div class="grid gap-4 @2xl:grid-cols-3">
                <div><p class="font-medium text-slate-500 text-base sm:text-sm">Waiting in the queue</p><p class="mt-1 text-xl font-semibold text-slate-950 tabular-nums">{{ $pendingContentRequests->total() }} {{ Str::plural('request', $pendingContentRequests->total()) }}</p></div>
                <div><p class="font-medium text-slate-500 text-base sm:text-sm">Content automation</p><p class="mt-1 font-medium text-slate-900">{{ $nextContentRun ? 'Scheduled' : ($contentPlan?->enabled ? 'Paused' : 'Not scheduled') }}</p><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $nextContentRun ? $nextContentRun->copy()->setTimezone($contentPlan->timezone)->format('D j M, H:i').' · '.$contentPlan->timezone : ($contentScheduleReason ?: 'Set your preferred days in Automation.') }}</p></div>
                <div><p class="font-medium text-slate-500 text-base sm:text-sm">Publishing</p><p class="mt-1 font-medium text-slate-900">{{ $hasContentDeliveryConnection ? 'Approval required' : 'Connection needed' }}</p><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $hasContentDeliveryConnection ? 'Review prepared changes before publishing.' : 'Choose a delivery connection to get started.' }}</p></div>
            </div>
        </div>
        <nav class="min-w-0" aria-label="Content sections">
            <div class="ui-tabs">
                @foreach ($contentSections as $key => $label)
                    <a id="content-section-tab-{{ $key }}" href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => $key]) }}" @if ($currentContentSection === $key) aria-current="page" @endif class="ui-tab">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
        <div id="content-section-plan" role="region" aria-labelledby="content-section-tab-plan" @if ($currentContentSection !== 'plan') hidden @endif>
            <section class="ui-panel ui-section">
                <h2 class="text-lg font-semibold">Rolling content plan</h2>
                <p class="mt-2 text-sm text-slate-600">Choose opportunities from the prioritised action list, then plan their timing. Planned work uses no Copilot credits and stays out of execution until approved into the queue. Dates guide planning; they do not force publication.</p>
                @error('coverage_reviewed')<p class="mt-3 text-sm text-rose-700" role="alert">Confirm coverage and business relevance before approving discovered content.</p>@enderror
                <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']) }}" class="ui-button ui-button-secondary mt-4">Explore opportunities</a>
                <div class="mt-5 divide-y divide-slate-200">
                    @forelse ($plannedContentRequests as $plannedRequest)
                        <article class="py-4">
                            <p class="text-sm font-medium">{{ $plannedRequest->planned_for?->format('j M Y') ?? 'Timing not set' }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $plannedRequest->instructions }}</p>
                            @if ($canManageWebsite)
                                <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $plannedRequest]) }}" class="mt-3 flex flex-wrap gap-3">
                                    @csrf @method('PATCH')
                                    <label for="planned-date-{{ $plannedRequest->id }}" class="sr-only">Planned date</label>
                                    <input id="planned-date-{{ $plannedRequest->id }}" type="date" name="planned_for" value="{{ $plannedRequest->planned_for?->format('Y-m-d') }}" class="ui-input">
                                    <button type="submit" name="action" value="plan" class="ui-button ui-button-secondary">Save timing</button>
                                    @if ($plannedRequest->discovery_context)
                                        <div class="basis-full text-sm text-slate-600"><p>{{ $plannedRequest->discovery_context['purpose'] }}</p><p class="mt-1">Intent: {{ $plannedRequest->discovery_context['search_intent'] }} · Estimated monthly searches: {{ number_format($plannedRequest->discovery_context['search_volume']) }}</p><p class="mt-1">{{ $plannedRequest->discovery_context['relevance_reason'] }}</p><label class="mt-3 flex items-start gap-2"><input type="checkbox" name="coverage_reviewed" value="1"><span>I have checked search intent, business relevance and existing coverage, and approve this brief for preparation.</span></label></div>
                                    @endif
                                    <button type="submit" name="action" value="enqueue" class="ui-button ui-button-primary">Approve into queue</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No content planned yet.</p>
                    @endforelse
                </div>
                {{ $plannedContentRequests->links() }}
            </section>
        </div>
        <div id="content-section-queue" role="region" aria-labelledby="content-section-tab-queue" @if ($currentContentSection !== 'queue') hidden @endif>
            @include('admin.websites.partials.content-queue')
        </div>
        <div id="content-section-activity" role="region" aria-labelledby="content-section-tab-activity" @if ($currentContentSection !== 'activity') hidden @endif>
            @include('admin.websites.partials.content-activity')
        </div>
        @if ($canManageWebsite)
            <div id="content-section-automation" role="region" aria-labelledby="content-section-tab-automation" @if ($currentContentSection !== 'automation') hidden @endif>
                @include('admin.websites.partials.content-automation')
            </div>
        @endif
        @if ($canUseSdk)
            <div id="content-section-sdk" role="region" aria-labelledby="content-section-tab-sdk" @if ($currentContentSection !== 'sdk') hidden @endif>
                @include('admin.websites.partials.content-sdk')
            </div>
        @endif
        <div id="content-section-connections" class="space-y-5" role="region" aria-labelledby="content-section-tab-connections" @if ($currentContentSection !== 'connections') hidden @endif>
            @include('admin.websites.partials.content-connections')
        </div>
    @endif
</div>

@php
    $contentPlan = $website->contentPlan;
    $contentSections = ['queue' => 'Queue', 'activity' => 'Activity'];
    if ($canManageWebsite) {
        $contentSections['automation'] = 'Automation';
    }
    $contentSections['connections'] = 'Connections';
    $requestedContentSection = request('content_section', 'queue');
    $currentContentSection = is_string($requestedContentSection) && array_key_exists($requestedContentSection, $contentSections) ? $requestedContentSection : 'queue';
    if ($errors->has('instructions')) {
        $currentContentSection = 'queue';
    } elseif ($canManageWebsite && collect(['enabled', 'weekday', 'hour', 'timezone', 'audience', 'guidance', 'additional_weekdays', 'additional_weekdays.*'])->contains(fn ($field) => $errors->has($field))) {
        $currentContentSection = 'automation';
    }
    $canSubmitContentRequest = $website->repository || (config('forms.pixel_ui_enabled') && $website->pixel_enabled);
@endphp
<div id="website-panel-content" class="space-y-6" role="region" aria-labelledby="website-tab-content" data-tab-panel="content" @if ($currentWebsiteSection !== 'content') hidden @endif>
    @unless ($canUseGrowthFeatures)
        <x-feature-upgrade-banner tier="Growth" title="Plan and request new content" description="Upgrade to Growth to submit content requests, plan improvements, and prepare reviewable website changes." />
        @include('admin.websites.partials.content-preview')
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
        <div id="content-section-queue" role="region" aria-labelledby="content-section-tab-queue" @if ($currentContentSection !== 'queue') hidden @endif>
            @if (! $hasContentDeliveryConnection)
                <div class="mb-5 rounded-lg border border-amber-950/10 bg-amber-50 p-4"><p class="font-medium text-amber-950">Set up your content connection</p><p class="mt-1 text-base text-pretty text-amber-900 sm:text-sm">Choose Sitewell Pixel, WordPress, or GitHub to prepare website changes. <a href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => 'connections']) }}" class="font-medium underline">View setup options</a> or <a href="{{ $contentSupportCallUrl }}" target="_blank" rel="noreferrer" class="font-medium underline">book a call with support</a>.</p></div>
            @endif
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
        <div id="content-section-connections" class="space-y-5" role="region" aria-labelledby="content-section-tab-connections" @if ($currentContentSection !== 'connections') hidden @endif>
            @include('admin.websites.partials.content-connections')
        </div>
    @endunless
</div>

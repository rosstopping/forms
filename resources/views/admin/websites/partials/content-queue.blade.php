<section aria-labelledby="content-requests-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 id="content-requests-title" class="text-lg font-semibold text-balance text-slate-950">Content queue</h2><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Choose what Sitewell works on next. Move important requests to the top.</p></div>
        <div class="text-sm"><a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']) }}" class="ui-button ui-button-secondary ui-button-small">Find SEO opportunities</a></div>
    </div>
    @if ($contentPrompt && Auth::user()?->isAdmin())
        <details id="content-ai-prompt" class="ui-panel mt-5 p-4" open>
            <summary class="cursor-pointer font-medium text-slate-950">AI content prompt</summary>
            <p class="mt-3 whitespace-pre-line text-base text-slate-700 sm:text-sm">{{ $contentPromptRequest->instructions }}</p>
            <p class="mt-2 text-base text-slate-600 sm:text-sm">Paste this into Codex or your preferred coding assistant. Copying keeps the request in its current state. Take it for manual work to prevent automation picking it up.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="ui-button ui-button-secondary ui-button-small js-copy-text" data-copy-target="content-request-ai-prompt" data-copy-label="Copy prompt" data-copied-label="Copied">Copy prompt</button>
                @if (! $contentPromptRequest->picked_up_at)
                    <form method="POST" action="{{ route('admin.content-requests.manual.take', [$website, $contentPromptRequest]) }}">
                        @csrf
                        <button type="submit" class="ui-button ui-button-secondary ui-button-small">Take for manual work</button>
                    </form>
                @endif
            </div>
            <label for="content-request-ai-prompt" class="ui-label mt-4 block">Prompt</label>
            <textarea id="content-request-ai-prompt" class="ui-input mt-1 h-72 w-full resize-y font-mono text-sm" readonly>{{ $contentPrompt }}</textarea>
        </details>
    @endif
    @if ($manualContentRequests->isNotEmpty())
        <section class="mt-5 border-t border-slate-950/10 pt-4" aria-labelledby="manual-content-title">
            <h3 id="manual-content-title" class="text-sm font-semibold text-slate-900">Manual work ({{ $manualContentRequests->total() }})</h3>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Reserved requests are excluded from automation until returned to the queue.</p>
            <div class="mt-3 space-y-3">
                @foreach ($manualContentRequests as $manualRequest)
                    <article class="ui-well p-3">
                        <p class="text-sm font-medium text-violet-700">Taken {{ $manualRequest->manual_started_at->diffForHumans() }}{{ $manualRequest->manualAssignee ? ' by '.$manualRequest->manualAssignee->name : '' }}</p>
                        <p class="mt-2 whitespace-pre-line break-words text-base text-slate-700 sm:text-sm">{{ $manualRequest->instructions }}</p>
                        @if (Auth::user()?->isAdmin())
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => 'queue', 'content_prompt' => $manualRequest->id]) }}#content-ai-prompt" class="ui-button ui-button-secondary ui-button-small">Show AI prompt</a>
                                <form method="POST" action="{{ route('admin.content-requests.manual.release', [$website, $manualRequest]) }}">
                                    @csrf
                                    <button type="submit" class="ui-button ui-button-secondary ui-button-small">Return to queue</button>
                                </form>
                                <form method="POST" action="{{ route('admin.content-requests.manual.complete', [$website, $manualRequest]) }}">
                                    @csrf
                                    <button type="submit" class="ui-button ui-button-secondary ui-button-small">Mark complete</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
            @if ($manualContentRequests->hasPages())<div class="mt-4">{{ $manualContentRequests->links() }}</div>@endif
        </section>
    @endif
    @if ($canManageWebsite && $canSubmitContentRequest)
        <details class="ui-panel mt-5 p-4" @if ($errors->has('instructions') || old('instructions')) open @endif>
            <summary class="cursor-pointer font-medium text-slate-950">Add a content request</summary>
            <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">Request a new page, an article, or an improvement to existing content.</p>
        <form method="POST" action="{{ route('admin.content-requests.store', $website) }}" class="mt-4">
            @csrf
            <label class="ui-label block" for="content-request-instructions">What would you like Sitewell to create or change?</label>
            <textarea id="content-request-instructions" name="instructions" rows="4" required maxlength="3000" class="ui-input mt-1 w-full" placeholder="For example: Create a new landing page or blog post for a specific category.">{{ old('instructions') }}</textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-slate-500 text-base sm:text-sm">Include the audience, useful keywords, desired location in the site, and any claims or qualifications that must be preserved. Up to 3,000 characters.</p>
                    @error('instructions')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="ui-button ui-button-primary">Add content request</button>
            </div>
        </form>

        </details>
    @elseif (! $canSubmitContentRequest)
        <p class="mt-4 rounded-lg bg-amber-50 p-4 text-base text-amber-900 sm:text-sm">Content requests need a GitHub repository or an enabled Pixel connection. <a href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => 'connections']) }}" class="font-medium underline">View connection options</a>.</p>
    @endif
        <div class="mt-5 border-t border-slate-950/10 pt-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-slate-900">Pending requests</h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-sm font-medium tabular-nums text-amber-800">{{ $pendingContentRequests->total() }}</span>
            </div>
            <div class="mt-3 max-h-144 space-y-3 overflow-y-auto overscroll-contain pr-2" tabindex="0" role="region" aria-label="Pending content requests">
                @forelse ($pendingContentRequests as $contentRequest)
                    @php
                        $queuePosition = $pendingContentRequests->firstItem() + $loop->index;
                        $queueHasErrors = (int) old('queue_request_id') === $contentRequest->id && $errors->any();
                        $queueState = $contentQueueStates[$contentRequest->id] ?? ['state' => 'ready', 'reason' => '', 'eligible_at' => null];
                    @endphp
                    <article class="rounded-lg border border-slate-950/10 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($queuePosition === 1 && $queueState['state'] === 'ready')
                                        <span class="rounded-full bg-teal-100 px-2.5 py-1 text-sm font-medium text-teal-800">Up next</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-sm font-medium tabular-nums text-amber-800">Queue #{{ $queuePosition }}</span>
                                    @endif
                                    @if ($contentRequest->bumped_at)
                                        <span class="rounded-full bg-violet-100 px-2.5 py-1 text-sm font-medium text-violet-800">Bumped</span>
                                    @endif
                                    <span class="text-sm text-slate-500">Added {{ $contentRequest->created_at->diffForHumans() }}{{ $contentRequest->creator ? ' by '.$contentRequest->creator->name : '' }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-line break-words text-base text-slate-700 sm:text-sm">{{ $contentRequest->instructions }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-700">
                                    {{ match ($queueState['state']) { 'strategy' => 'Waiting for strategy review', 'held' => 'On hold', 'cooldown' => 'Waiting for cooldown', 'review' => 'Waiting for review', 'preflight' => 'Waiting for dependency check', default => 'Ready for preflight' } }}
                                    @if ($queueState['eligible_at'])
                                        — eligible from {{ \Illuminate\Support\Carbon::parse($queueState['eligible_at'])->timezone($website->contentPlan?->timezone ?? config('app.timezone'))->format('j M Y, H:i T') }}
                                    @endif
                                </p>
                                <p class="mt-1 text-sm text-slate-500">{{ $queueState['reason'] }}</p>
                                @if ($contentRequest->seoImpact)
                                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $contentRequest->seoImpact->id]) }}" class="mt-2 inline-block text-sm font-medium text-slate-700 underline">View measurable brief</a>
                                @endif
                            </div>
                            @if ($canManageWebsite)
                                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                    @if (Auth::user()?->isAdmin())
                                        <a href="{{ route('admin.websites.section', [$website, 'content', 'content_section' => 'queue', 'content_prompt' => $contentRequest->id]) }}#content-ai-prompt" class="ui-button ui-button-secondary">Show AI prompt</a>
                                    @endif
                                    @if ($queuePosition !== 1)
                                        <form method="POST" action="{{ route('admin.content-requests.bump', [$website, $contentRequest]) }}">
                                            @csrf
                                            <button type="submit" class="ui-button ui-button-secondary w-full sm:w-auto">Bump to top</button>
                                        </form>
                                    @endif
                                    @if ($contentRequest->held_at)
                                        <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $contentRequest]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="release">
                                            <button type="submit" class="ui-button ui-button-secondary">Release hold</button>
                                        </form>
                                    @endif
                                    @if (! $contentRequest->budget_reserved_at)
                                    <form method="POST" action="{{ route('admin.content-requests.destroy', [$website, $contentRequest]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-button ui-button-secondary w-full sm:w-auto">Remove</button>
                                    </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if ($canManageWebsite)
                            <details class="mt-3" @if ($queueHasErrors) open @endif>
                                <summary class="cursor-pointer text-sm font-medium">Queue controls</summary>
                                <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $contentRequest]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="plan">
                                    <input type="hidden" name="queue_request_id" value="{{ $contentRequest->id }}">
                                    <div><label for="queue-planned-date-{{ $contentRequest->id }}" class="ui-label block">Proposed date (optional)</label><input id="queue-planned-date-{{ $contentRequest->id }}" type="date" name="planned_for" class="ui-input"></div>
                                    <button type="submit" class="ui-button ui-button-secondary">Move to content plan</button>
                                </form>
                                @if (! $contentRequest->budget_reserved_at)
                                <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $contentRequest]) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="action" value="classify">
                                    <input type="hidden" name="queue_request_id" value="{{ $contentRequest->id }}">
                                    <div><label for="work-type-{{ $contentRequest->id }}" class="ui-label block">Work type</label>
                                    <select id="work-type-{{ $contentRequest->id }}" name="work_type" class="ui-input">
                                        @foreach (['unspecified' => 'Needs classification', 'new_article' => 'New article', 'new_page' => 'New landing page', 'optimisation' => 'Existing-page optimisation'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($contentRequest->work_type ?? 'unspecified') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select></div>
                                    <button class="ui-button ui-button-secondary" type="submit">Save work type</button>
                                </form>
                                @else
                                    <p class="mt-3 text-sm text-slate-500">Preparation usage is reserved. Work type is fixed; hold the request to retain its history.</p>
                                @endif
                                @if ($queueHasErrors)
                                    @foreach ($errors->all() as $queueError)
                                        <p class="mt-2 text-sm text-red-700">{{ $queueError }}</p>
                                    @endforeach
                                @endif
                                @if (! $contentRequest->held_at)
                                    <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $contentRequest]) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="hold">
                                        <input type="hidden" name="queue_request_id" value="{{ $contentRequest->id }}">
                                        <label class="ui-label block" for="hold-reason-{{ $contentRequest->id }}">Reason for holding this request</label>
                                        <input id="hold-reason-{{ $contentRequest->id }}" name="hold_reason" required maxlength="500" class="ui-input mt-1 w-full" value="{{ $queueHasErrors ? old('hold_reason') : '' }}">
                                        <button type="submit" class="ui-button ui-button-secondary mt-2">Put on hold</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.content-requests.queue.update', [$website, $contentRequest]) }}" class="mt-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="dependencies">
                                    <input type="hidden" name="queue_request_id" value="{{ $contentRequest->id }}">
                                    <label class="ui-label block" for="required-urls-{{ $contentRequest->id }}">Required pages</label>
                                    <p class="mt-1 text-sm text-slate-500">Pages that must change to complete this request, such as its guides listing. One website URL per line, up to five.</p>
                                    <textarea id="required-urls-{{ $contentRequest->id }}" name="urls" rows="2" class="ui-input mt-1 w-full">{{ implode("\n", $contentRequest->dependencies['urls'] ?? []) }}</textarea>
                                    @if (Auth::user()?->isAdmin())
                                        <label class="ui-label mt-3 block" for="required-files-{{ $contentRequest->id }}">Required repository files</label>
                                        <p class="mt-1 text-sm text-slate-500">Exact paths from the repository root, including any required sitemap or listing files. One per line, up to five. GitHub checks these before Copilot starts.</p>
                                        <textarea id="required-files-{{ $contentRequest->id }}" name="files" rows="2" class="ui-input mt-1 w-full">{{ implode("\n", $contentRequest->dependencies['files'] ?? []) }}</textarea>
                                    @endif
                                    <button type="submit" class="ui-button ui-button-secondary mt-2">Save dependencies</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="rounded-lg bg-slate-50 p-3 text-slate-500 text-base sm:text-sm">Your queue is clear. Add a request or choose an SEO opportunity to plan your next improvement.</p>
                @endforelse
            </div>
            @if ($pendingContentRequests->hasPages())
                <div class="mt-4">{{ $pendingContentRequests->links() }}</div>
            @endif
        </div>

</section>

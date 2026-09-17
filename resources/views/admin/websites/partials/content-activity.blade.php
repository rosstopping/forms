<section class="space-y-6" aria-labelledby="content-activity-title">
    <div><h2 id="content-activity-title" class="text-lg font-semibold text-balance text-slate-950">Activity and review</h2><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Follow requests from preparation to review. Work being prepared is not necessarily live.</p></div>
    @if (config('forms.pixel_ui_enabled') && $website->pixel_enabled)
        <p class="text-base text-slate-600 sm:text-sm">Page title and description drafts are reviewed in <a href="{{ route('admin.websites.section', [$website, 'pixel']) }}" class="font-medium text-slate-900 underline">Pixel</a>.</p>
    @endif
        @if ($actionedContentRequests->isNotEmpty())
            <div class="mt-5 border-t border-slate-950/10 pt-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Request history</h3>
                        <p class="mt-1 text-slate-500 text-base sm:text-sm">Requests that have been picked up, with their latest preparation or review status.</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-sm font-medium tabular-nums text-emerald-800">{{ $actionedContentRequests->total() }}</span>
                </div>
                <div class="mt-3 max-h-144 space-y-3 overflow-y-auto overscroll-contain pr-2" tabindex="0" role="region" aria-label="Content request activity">
                    @foreach ($actionedContentRequests as $contentRequest)
                        <article class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-sm font-medium text-emerald-800">Picked up</span>
                                        @if ($contentRequest->generation)
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-sm font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $contentRequest->generation->status) }}</span>
                                        @endif
                                        <span class="text-sm text-slate-500">Picked up {{ $contentRequest->picked_up_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line break-words text-base text-slate-700 sm:text-sm">{{ $contentRequest->instructions }}</p>
                                </div>
                                @if ($canManageWebsite && $contentRequest->generation?->pull_request_url)
                                    <a href="{{ $contentRequest->generation->pull_request_url }}" target="_blank" rel="noreferrer" class="ui-button ui-button-secondary ui-button-small">View pull request</a>
                                @elseif ($canManageWebsite && $contentRequest->generation?->copilot_task_url)
                                    <a href="{{ $contentRequest->generation->copilot_task_url }}" target="_blank" rel="noreferrer" class="ui-button ui-button-secondary ui-button-small">View generation task</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    @if ($actionedContentRequests->isEmpty())
        <div class="ui-well p-5"><p class="font-medium text-slate-900">No requests have been picked up yet</p><p class="mt-1 text-base text-slate-600 sm:text-sm">Once Sitewell starts preparing a queued request, its progress appears here.</p></div>
    @endif
    @if ($actionedContentRequests->hasPages())<div>{{ $actionedContentRequests->links() }}</div>@endif
    <details class="border-t border-slate-950/10 pt-4" @if ($contentPlan?->generations->contains('status', \App\Models\ContentGeneration::STATUS_PULL_REQUEST_OPEN)) open @endif><summary class="cursor-pointer font-medium text-slate-900">Recent generation runs and reviews</summary>
            @if ($contentPlan?->generations->isNotEmpty())
                <div class="mt-5 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left text-sm text-slate-500"><th class="whitespace-nowrap py-2">Date</th><th class="whitespace-nowrap">Status</th><th class="whitespace-nowrap">Pull request</th><th class="text-right">Actions</th></tr></thead><tbody>@foreach ($contentPlan->generations as $generation)<tr class="border-b"><td class="py-2">{{ $generation->scheduled_for->toFormattedDateString() }}</td><td>{{ str_replace('_', ' ', $generation->status) }}@if ($generation->skip_reason)<p class="mt-1 max-w-sm text-slate-500 text-base sm:text-sm">{{ $generation->skip_reason }}</p>@endif</td><td>@if ($canManageWebsite && $generation->pull_request_url)<a class="font-medium underline" href="{{ $generation->pull_request_url }}">#{{ $generation->pull_request_number }}</a>@else — @endif</td><td><div class="flex justify-end gap-2">@if (Auth::user()?->isAdmin() && $generation->pull_request_number && $generation->status === \App\Models\ContentGeneration::STATUS_PULL_REQUEST_OPEN)<form method="POST" action="{{ route('admin.content-generations.sync', [$website, $generation]) }}">@csrf<input type="hidden" name="content_section" value="activity"><button type="submit" class="ui-button ui-button-secondary ui-button-small">Check GitHub status</button></form><form method="POST" action="{{ route('admin.content-generations.destroy', [$website, $generation]) }}">@csrf @method('DELETE')<input type="hidden" name="content_section" value="activity"><button type="submit" class="ui-button ui-button-danger ui-button-small">Cancel</button></form>@else — @endif</div></td></tr>@endforeach</tbody></table></div>
            @endif
    @if (! $contentPlan?->generations->isNotEmpty())<p class="mt-3 text-base text-slate-500 sm:text-sm">No generation runs yet.</p>@endif
    </details>
</section>

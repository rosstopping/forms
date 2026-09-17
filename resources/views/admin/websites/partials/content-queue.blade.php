<section aria-labelledby="content-requests-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><h2 id="content-requests-title" class="text-lg font-semibold text-balance text-slate-950">Content queue</h2><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Choose what Sitewell works on next. Move important requests to the top.</p></div>
        <div class="text-sm"><a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']) }}" class="ui-button ui-button-secondary ui-button-small">Find SEO opportunities</a></div>
    </div>
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
                    @endphp
                    <article class="rounded-lg border border-slate-950/10 p-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($queuePosition === 1)
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
                                @if ($contentRequest->seoImpact)
                                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $contentRequest->seoImpact->id]) }}" class="mt-2 inline-block text-sm font-medium text-slate-700 underline">View measurable brief</a>
                                @endif
                            </div>
                            @if ($canManageWebsite)
                                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                                    @if ($queuePosition !== 1)
                                        <form method="POST" action="{{ route('admin.content-requests.bump', [$website, $contentRequest]) }}">
                                            @csrf
                                            <button type="submit" class="ui-button ui-button-secondary w-full sm:w-auto">Bump to top</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.content-requests.destroy', [$website, $contentRequest]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-button ui-button-secondary w-full sm:w-auto">Remove</button>
                                    </form>
                                </div>
                            @endif
                        </div>
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

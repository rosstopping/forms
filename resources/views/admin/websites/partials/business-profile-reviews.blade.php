<section id="business-reviews" class="ui-panel scroll-mt-6 overflow-hidden" aria-labelledby="business-reviews-heading">
    <div class="space-y-4 border-b border-slate-950/10 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h3 id="business-reviews-heading" class="text-lg font-semibold text-balance text-slate-950">Review inbox</h3>
                <p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Replies are drafted automatically. Review, edit, and approve each one before it reaches Google.</p>
                <p class="mt-2 text-slate-500 text-base sm:text-sm">{{ $profile->last_synced_at ? 'Last synced '.$profile->last_synced_at->diffForHumans().'.' : 'Waiting for the first review sync.' }} Checked hourly.</p>
            </div>
            @if ($canManageWebsite)
                <div class="flex flex-wrap gap-2">
                    @if ($reviewRetryCount > 0)<form method="POST" action="{{ route('admin.business-profile.reviews.drafts', $website) }}">@csrf<button type="submit" class="{{ $profileButton }}">Prepare remaining replies ({{ $reviewRetryCount }})</button></form>@endif
                    <form method="POST" action="{{ route('admin.business-profile.reviews.sync', $website) }}">@csrf<button type="submit" class="{{ $profileButton }}">Sync reviews</button></form>
                </div>
            @endif
        </div>
        <nav class="ui-tabs" aria-label="Review filters">
            @foreach (['unreplied' => 'Needs a reply', 'replied' => 'Replied', 'all' => 'All reviews'] as $value => $label)
                <a href="{{ route('admin.websites.section', [$website, 'business-profile', ...request()->only('bp_posts'), 'bp_reviews' => $value]) }}#business-reviews" @if ($businessReviewFilter === $value) aria-current="page" @endif class="ui-tab">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <div class="max-h-144 divide-y divide-slate-950/10 overflow-y-auto overscroll-contain" tabindex="0" role="region" aria-label="Customer reviews">
        @forelse ($businessReviews as $review)
            <article class="grid gap-5 p-5 md:grid-cols-2">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3"><h4 class="font-medium text-slate-950">{{ $review->reviewer_name ?: 'Customer' }}</h4><p class="text-amber-700 text-base sm:text-sm" aria-label="{{ $review->star_rating }} out of 5 stars">{{ str_repeat('★', $review->star_rating) }}</p></div>
                    @if ($review->reviewed_at)<p class="mt-1 text-slate-500 text-base sm:text-sm">{{ $review->reviewed_at->format('j M Y') }}</p>@endif
                    <p class="mt-3 whitespace-pre-line text-base break-words text-slate-600 sm:text-sm">{{ $review->comment ?: 'Rating without a written comment.' }}</p>
                </div>
                <div class="min-w-0">
                    @if ($review->reply_status === 'pending_approval')
                        <p class="font-medium text-amber-800 text-base sm:text-sm">Reply ready for approval</p>
                        @if ($canManageWebsite)
                            <form method="POST" action="{{ route('admin.business-profile.reviews.update', [$website, $review]) }}" class="mt-2 space-y-3">@csrf @method('PUT')<label for="business-reply-{{ $review->id }}" class="ui-label sr-only">Reply to {{ $review->reviewer_name ?: 'customer' }}</label><textarea id="business-reply-{{ $review->id }}" name="reply" rows="4" required maxlength="1200" class="{{ $profileInput }}">{{ $review->suggested_reply }}</textarea><button type="submit" class="ui-button ui-button-secondary ui-button-small">Approve & reply</button></form>
                        @else
                            <p class="mt-2 whitespace-pre-line text-base break-words text-slate-600 sm:text-sm">{{ $review->suggested_reply }}</p>
                        @endif
                    @elseif ($review->reply_status === 'replied')
                        <p class="font-medium text-emerald-800 text-base sm:text-sm">Published reply</p><p class="mt-2 whitespace-pre-line text-base break-words text-slate-600 sm:text-sm">{{ $review->google_reply }}</p>
                    @elseif ($review->reply_status === 'generating')
                        <p class="font-medium text-slate-900 text-base sm:text-sm">Preparing reply</p><p class="mt-2 text-base text-pretty text-slate-500 sm:text-sm">Refresh shortly to review the draft. Nothing has been published.</p>
                    @elseif ($review->reply_status === 'failed')
                        <p class="font-medium text-rose-700 text-base sm:text-sm">Reply draft failed</p><p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">Use “Prepare remaining replies” above to retry all failed and unanswered reviews together.</p>
                    @else
                        <p class="font-medium text-slate-900 text-base sm:text-sm">Awaiting automatic draft</p><p class="mt-2 text-base text-pretty text-slate-500 sm:text-sm">A reply will be prepared at the next sync. Use “Prepare remaining replies” to start sooner.</p>
                    @endif
                </div>
            </article>
        @empty
            <div class="p-8"><p class="font-medium text-slate-900">{{ $businessReviewFilter === 'unreplied' ? 'No reviews waiting for a reply' : 'No reviews here yet' }}</p><p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">{{ $businessReviewFilter === 'unreplied' && $businessReviewCounts->sum() > 0 ? 'You are up to date. New reviews will appear here with replies prepared for approval.' : 'Reviews will appear after the next sync. You can also sync them now.' }}</p></div>
        @endforelse
    </div>
    @if ($businessReviews->hasPages())<div class="border-t border-slate-950/10 p-5">{{ $businessReviews->links() }}</div>@endif
</section>

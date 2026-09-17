<div id="business-post-queue" class="scroll-mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h4 class="font-semibold text-slate-950">Post queue</h4>
        <nav class="ui-tabs" aria-label="Post queue filters">
            @foreach (['active' => 'In progress', 'published' => 'Published'] as $value => $label)
                <a href="{{ route('admin.websites.section', [$website, 'business-profile', ...request()->only('bp_reviews'), 'bp_posts' => $value]) }}#business-post-queue" @if ($businessPostFilter === $value) aria-current="page" @endif class="ui-tab">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">{{ $profile->weekly_posts_enabled ? 'Queued topics are drafted oldest first on your weekly schedule. You can also draft a topic now.' : 'Weekly drafting is off. Draft a topic now, or turn on the weekly schedule alongside your queue.' }}</p>
    <div class="mt-4 max-h-144 space-y-4 overflow-y-auto overscroll-contain pr-2" tabindex="0" role="region" aria-label="Queued posts and drafts">
        @forelse ($businessPosts as $post)
            <article class="rounded-lg border border-slate-950/10 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <p @class(['rounded px-2 py-1 font-medium', 'bg-amber-50 text-amber-900' => $post->status === 'pending_approval', 'bg-rose-50 text-rose-800' => $post->status === 'failed', 'bg-emerald-50 text-emerald-800' => $post->status === 'published', 'bg-slate-100 text-slate-700' => in_array($post->status, ['queued', 'generating'])])>{{ ['queued' => 'Queued', 'generating' => 'Drafting', 'pending_approval' => 'Ready for approval', 'published' => 'Published', 'failed' => 'Draft failed'][$post->status] ?? ucfirst($post->status) }}</p>
                    <p class="text-slate-500">{{ $post->published_at ? $post->published_at->format('j M Y') : 'Added '.$post->created_at->format('j M') }}</p>
                </div>
                <p class="mt-3 text-base font-medium text-pretty break-words text-slate-900 sm:text-sm">{{ $post->topic ?: 'Business update' }}</p>
                @if ($post->status === 'pending_approval' && $canManageWebsite)
                    <form method="POST" action="{{ route('admin.business-profile.posts.update', [$website, $post]) }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <div><label for="business-summary-{{ $post->id }}" class="ui-label">Post text</label><textarea id="business-summary-{{ $post->id }}" name="summary" rows="5" required maxlength="1500" class="{{ $profileInput }} mt-1">{{ $post->summary }}</textarea></div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label for="business-cta-{{ $post->id }}" class="ui-label">Button</label><select id="business-cta-{{ $post->id }}" name="call_to_action_type" class="{{ $profileInput }} mt-1">@foreach (['' => 'No button', 'LEARN_MORE' => 'Learn more', 'BOOK' => 'Book', 'ORDER' => 'Order', 'SIGN_UP' => 'Sign up', 'CALL' => 'Call'] as $value => $label)<option value="{{ $value }}" @selected(($post->call_to_action_type ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div><label for="business-url-{{ $post->id }}" class="ui-label">Button link</label><input id="business-url-{{ $post->id }}" type="url" name="call_to_action_url" value="{{ $post->call_to_action_url }}" maxlength="2048" placeholder="https://" class="{{ $profileInput }} mt-1"></div>
                        </div>
                        <button type="submit" class="ui-button ui-button-secondary ui-button-small">Approve & publish</button>
                    </form>
                @elseif ($post->summary)
                    <p class="mt-3 whitespace-pre-line text-base break-words text-slate-600 sm:text-sm">{{ $post->summary }}</p>
                @endif
                @if ($post->status === 'generating')<p class="mt-3 text-base text-slate-500 sm:text-sm">Your draft is being prepared. Refresh this page shortly to review it.</p>@endif
                @if ($post->status === 'failed')<p class="mt-3 text-base text-rose-700 sm:text-sm">We could not prepare this draft. Retry or remove it from the queue.</p>@endif
                @if ($canManageWebsite && in_array($post->status, ['queued', 'failed', 'pending_approval']))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if (in_array($post->status, ['queued', 'failed']))<form method="POST" action="{{ route('admin.business-profile.posts.draft', [$website, $post]) }}">@csrf<button type="submit" class="{{ $profileButton }}">{{ $post->status === 'failed' ? 'Retry draft' : 'Draft now' }}</button></form>@endif
                        <form method="POST" action="{{ route('admin.business-profile.posts.destroy', [$website, $post]) }}">@csrf @method('DELETE')<button type="submit" class="{{ $profileButton }}">Remove</button></form>
                    </div>
                @endif
            </article>
        @empty
            <div class="ui-well p-5"><p class="font-medium text-slate-900">{{ $businessPostFilter === 'published' ? 'No posts published yet' : 'Your post queue is clear' }}</p><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">{{ $businessPostFilter === 'published' ? 'Posts appear here after you approve and publish them to Google.' : 'Add a suggested post or your own idea above. Each draft will come back here for approval.' }}</p></div>
        @endforelse
    </div>
    @if ($businessPosts->hasPages())<div class="mt-4">{{ $businessPosts->links() }}</div>@endif
</div>

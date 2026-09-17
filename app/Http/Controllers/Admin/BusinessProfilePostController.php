<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfilePost;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use App\Services\BusinessProfilePostQueuer;
use App\Services\BusinessProfilePostSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessProfilePostController extends Controller
{
    public function store(Request $request, Website $website, BusinessProfilePostSuggestions $suggestions): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->businessProfileConnection()->firstOrFail();
        if (blank($connection->location_name)) {
            return to_route('admin.business-profile.locations', $website)->with('error', 'Select a Google Business Profile location before queuing posts.');
        }
        $ideas = $suggestions->forConnection($connection);
        $data = $request->validate([
            'suggestion' => ['nullable', Rule::in(array_keys($ideas))],
            'topic' => ['required_without:suggestion', 'nullable', 'string', 'max:1000'],
        ]);
        $topic = filled($data['suggestion'] ?? null) ? $ideas[$data['suggestion']]['topic'] : $data['topic'];
        DB::transaction(function () use ($connection, $topic): void {
            $connection->newQuery()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $connection->posts()->where('topic', $topic)->where('status', '!=', BusinessProfilePost::STATUS_PUBLISHED)->exists()) {
                $connection->posts()->create(['topic' => $topic, 'status' => BusinessProfilePost::STATUS_QUEUED]);
            }
        });

        return back()->with('status', 'Topic added to the post queue. Draft it now or let your weekly schedule pick it up.');
    }

    public function draft(Request $request, Website $website, BusinessProfilePost $post, BusinessProfilePostQueuer $posts): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($post->connection->website_id === $website->id, 404);
        if (blank($post->connection->location_name)) {
            return to_route('admin.business-profile.locations', $website)->with('error', 'Select a Google Business Profile location before drafting posts.');
        }
        abort_unless($posts->draft($post), 422);

        return back()->with('status', 'Your post is being drafted. It will be ready here for approval.');
    }

    public function destroy(Request $request, Website $website, BusinessProfilePost $post): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($post->connection->website_id === $website->id, 404);
        $deleted = BusinessProfilePost::query()->whereKey($post->id)
            ->whereIn('status', [BusinessProfilePost::STATUS_QUEUED, BusinessProfilePost::STATUS_FAILED, BusinessProfilePost::STATUS_PENDING_APPROVAL])->delete();
        abort_unless($deleted, 422);

        return back()->with('status', 'Post removed from the queue.');
    }

    public function update(Request $request, Website $website, BusinessProfilePost $post, BusinessProfileClient $client): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($post->connection->website_id === $website->id && $post->status === BusinessProfilePost::STATUS_PENDING_APPROVAL, 422);
        $data = $request->validate(['summary' => ['required', 'string', 'max:1500'], 'call_to_action_type' => ['nullable', 'string', 'in:LEARN_MORE,BOOK,ORDER,SIGN_UP,CALL'], 'call_to_action_url' => ['nullable', 'url', 'max:2048', Rule::requiredIf(filled($request->input('call_to_action_type')))]]);
        $published = $client->createPost($post->connection, $data['summary'], $data['call_to_action_type'] ?? null, $data['call_to_action_url'] ?? null);
        $post->update([...$data, 'status' => BusinessProfilePost::STATUS_PUBLISHED, 'google_post_name' => $published['name'] ?? null, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'published_at' => now()]);

        return back()->with('status', 'Google post approved and published.');
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}

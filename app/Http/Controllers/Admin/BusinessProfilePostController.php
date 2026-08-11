<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBusinessProfilePost;
use App\Models\BusinessProfilePost;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessProfilePostController extends Controller
{
    public function store(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $data = $request->validate(['topic' => ['nullable', 'string', 'max:1000']]);
        $post = $website->businessProfileConnection()->firstOrFail()->posts()->create(['topic' => $data['topic'] ?? null, 'status' => BusinessProfilePost::STATUS_GENERATING]);
        GenerateBusinessProfilePost::dispatch($post);

        return back()->with('status', 'Google post draft queued for generation.');
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

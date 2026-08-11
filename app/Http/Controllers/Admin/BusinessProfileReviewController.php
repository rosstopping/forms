<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBusinessProfileReviewReply;
use App\Models\BusinessProfileReview;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessProfileReviewController extends Controller
{
    public function store(Request $request, Website $website, BusinessProfileReview $review): RedirectResponse
    {
        $this->authorizeReview($request, $website, $review);
        abort_unless(in_array($review->reply_status, [BusinessProfileReview::STATUS_UNANSWERED, BusinessProfileReview::STATUS_FAILED], true), 422);
        $review->update(['reply_status' => BusinessProfileReview::STATUS_GENERATING, 'error' => null]);
        GenerateBusinessProfileReviewReply::dispatch($review);

        return back()->with('status', 'Review reply draft queued.');
    }

    public function update(Request $request, Website $website, BusinessProfileReview $review, BusinessProfileClient $client): RedirectResponse
    {
        $this->authorizeReview($request, $website, $review);
        abort_unless($review->reply_status === BusinessProfileReview::STATUS_PENDING_APPROVAL, 422);
        $data = $request->validate(['reply' => ['required', 'string', 'max:1200']]);
        $client->replyToReview($review->connection, $review->google_review_name, $data['reply']);
        $review->update(['suggested_reply' => $data['reply'], 'google_reply' => $data['reply'], 'reply_status' => BusinessProfileReview::STATUS_REPLIED, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'replied_at' => now()]);

        return back()->with('status', 'Review reply approved and published.');
    }

    protected function authorizeReview(Request $request, Website $website, BusinessProfileReview $review): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($review->connection->website_id === $website->id, 404);
    }
}

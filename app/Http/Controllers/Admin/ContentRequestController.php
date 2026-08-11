<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentRequestRequest;
use App\Models\ContentRequest;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ContentRequestController extends Controller
{
    public function store(StoreContentRequestRequest $request, Website $website): RedirectResponse
    {
        $website->contentRequests()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return Redirect::route('admin.websites.show', $website)->with('status', 'Content request added for the next generation.');
    }

    public function destroy(Request $request, Website $website, ContentRequest $contentRequest): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $website->user_id === $request->user()?->id, 403);
        abort_unless($contentRequest->website_id === $website->id, 404);
        abort_if($contentRequest->picked_up_at, 422, 'A content request cannot be removed after Copilot has picked it up.');

        $contentRequest->delete();

        return Redirect::route('admin.websites.show', $website)->with('status', 'Content request removed.');
    }
}

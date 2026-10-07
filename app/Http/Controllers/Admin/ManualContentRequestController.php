<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentRequest;
use App\Models\Website;
use App\Services\ManualContentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualContentRequestController extends Controller
{
    public function take(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $manual->take($contentRequest, $request->user());

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'queue', 'content_prompt' => $contentRequest->id])
            ->with('status', 'Request reserved for manual work. It will not be picked up by automation.');
    }

    public function release(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $manual->returnToQueue($contentRequest);

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'queue'])->with('status', 'Request returned to the content queue.');
    }

    public function complete(Request $request, Website $website, ContentRequest $contentRequest, ManualContentRequest $manual): RedirectResponse
    {
        $this->authorizeRequest($request, $website, $contentRequest);
        $manual->complete($contentRequest);

        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'activity'])->with('status', 'Manual work marked complete. This does not confirm publication or start SEO measurement.');
    }

    private function authorizeRequest(Request $request, Website $website, ContentRequest $contentRequest): void
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
        abort_unless($contentRequest->website_id === $website->id, 404);
    }
}

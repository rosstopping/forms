<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ContentRequestPixelController extends Controller
{
    public function __invoke(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless(config('forms.pixel_ui_enabled') && $website->pixel_enabled, 422, 'Enable Pixel before preparing content todos.');

        $contentRequests = $website->contentRequests()
            ->whereNull('picked_up_at')
            ->whereNull('pixel_processed_at')
            ->oldest()
            ->limit(20)
            ->get();

        foreach ($contentRequests as $contentRequest) {
            GenerateContentRequestPixelOptimisations::dispatch($contentRequest, $request->user());
        }

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'pixel'])
            ->with('status', $contentRequests->isEmpty()
                ? 'There are no unprocessed content todos for Pixel.'
                : 'Sitewell queued '.$contentRequests->count().' content '.($contentRequests->count() === 1 ? 'todo' : 'todos').' for safe Pixel drafting.');
    }
}

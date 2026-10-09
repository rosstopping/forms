<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSeoWinRequest;
use App\Models\SeoWin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeoWinController extends Controller
{
    public function update(UpdateSeoWinRequest $request, SeoWin $seoWin): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $seoWin, $data): void {
            $win = SeoWin::whereKey($seoWin->id)->lockForUpdate()->firstOrFail();
            if ($win->shared_at || $win->dismissed_at) {
                throw ValidationException::withMessages(['action' => 'This win has already been shared or dismissed.']);
            }
            switch ($data['action']) {
                case 'save':
                case 'approve':
                    $win->client_draft = trim($data['client_draft']);
                    if ($win->client_draft === '') {
                        throw ValidationException::withMessages(['client_draft' => 'Enter a client update before approving.']);
                    }
                    $win->approved_at = $data['action'] === 'approve' ? now() : null;
                    $win->approved_by = $data['action'] === 'approve' ? $request->user()->id : null;
                    break;
                case 'share':
                    if (! $win->approved_at) {
                        throw ValidationException::withMessages(['action' => 'Approve the saved update before marking it as shared.']);
                    }
                    $win->shared_at = now();
                    $win->shared_by = $request->user()->id;
                    $win->shared_text = $win->client_draft;
                    break;
                case 'dismiss':
                    $win->dismissed_at = now();
                    $win->approved_at = null;
                    $win->approved_by = null;
                    break;
            }
            $win->save();
        });

        return redirect()->route('admin.overview', ['hub' => 'wins', 'site_id' => $seoWin->website_id, 'signal_category' => $seoWin->category])->with('status', 'Win updated. No message was sent.');
    }
}

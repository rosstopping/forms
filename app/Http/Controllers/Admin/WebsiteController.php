<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class WebsiteController extends Controller
{
    public function index(Request $request)
    {
        $query = Website::query();

        if (! $request->user()?->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $websites = $query
            ->withCount('forms')
            ->withCount('submissions')
            ->latest('created_at')
            ->paginate(15);

        return view('admin.websites.index', compact('websites'));
    }

    public function show(Website $website)
    {
        $user = Auth::user();

        abort_unless($user?->isAdmin() || $website->user_id === $user?->id, 403);

        $website->load(['domains', 'forms' => fn ($query) => $query->latest('created_at'), 'submissions' => fn ($query) => $query->latest('created_at')->limit(10)]);

        return view('admin.websites.show', compact('website'));
    }

    public function update(Request $request, Website $website)
    {
        $user = Auth::user();

        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $website->fill($data)->save();

        return Redirect::route('admin.websites.show', $website)->with('status', 'Website owner updated.');
    }
}

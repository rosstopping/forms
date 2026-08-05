<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index(Request $request)
    {
        $websites = Website::query()
            ->withCount('forms')
            ->withCount('submissions')
            ->latest('created_at')
            ->paginate(15);

        return view('admin.websites.index', compact('websites'));
    }

    public function show(Website $website)
    {
        $website->load(['domains', 'forms' => fn ($query) => $query->latest('created_at'), 'submissions' => fn ($query) => $query->latest('created_at')->limit(10)]);

        return view('admin.websites.show', compact('website'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $userId = $user?->id;

        $websiteQuery = Website::query();
        $formQuery = Form::query();
        $submissionQuery = FormSubmission::query();

        if (! $user?->isAdmin()) {
            $websiteQuery->where('user_id', $userId);
            $formQuery->whereHas('website', fn ($query) => $query->where('user_id', $userId));
            $submissionQuery->whereHas('website', fn ($query) => $query->where('user_id', $userId));
        }

        return view('admin.dashboard', [
            'websiteCount' => $websiteQuery->count(),
            'formCount' => $formQuery->count(),
            'submissionCount' => $submissionQuery->count(),
            'submissionToday' => $submissionQuery->whereDate('created_at', today())->count(),
            'spamCount' => $submissionQuery->where('is_spam', true)->count(),
            'emailFailures' => $submissionQuery->whereNotNull('email_failed_at')->count(),
            'webhookFailures' => $submissionQuery->whereNotNull('webhook_failed_at')->count(),
            'recentWebsites' => $websiteQuery->latest('created_at')->take(5)->get(),
            'recentForms' => $formQuery->latest('created_at')->take(5)->get(),
            'recentSubmissions' => $submissionQuery->latest('created_at')->take(5)->get(),
        ]);
    }
}

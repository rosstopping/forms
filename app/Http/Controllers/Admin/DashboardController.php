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
        return view('admin.dashboard', [
            'websiteCount' => Website::query()->count(),
            'formCount' => Form::query()->count(),
            'submissionCount' => FormSubmission::query()->count(),
            'submissionToday' => FormSubmission::query()->whereDate('created_at', today())->count(),
            'spamCount' => FormSubmission::query()->where('is_spam', true)->count(),
            'emailFailures' => FormSubmission::query()->whereNotNull('email_failed_at')->count(),
            'webhookFailures' => FormSubmission::query()->whereNotNull('webhook_failed_at')->count(),
            'recentWebsites' => Website::query()->latest('created_at')->take(5)->get(),
            'recentForms' => Form::query()->latest('created_at')->take(5)->get(),
            'recentSubmissions' => FormSubmission::query()->latest('created_at')->take(5)->get(),
        ]);
    }
}

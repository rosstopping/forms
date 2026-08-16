<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportWebsiteAiQuestionRequest;
use App\Mail\WebsiteAiQuestionReported;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class WebsiteAiQuestionReportController extends Controller
{
    public function store(ReportWebsiteAiQuestionRequest $request, Website $website, WebsiteAiQuestion $websiteAiQuestion): RedirectResponse
    {
        abort_unless(in_array($websiteAiQuestion->status, ['completed', 'failed'], true), 422);

        $wasReported = DB::transaction(function () use ($request, $websiteAiQuestion): bool {
            $question = WebsiteAiQuestion::query()->lockForUpdate()->findOrFail($websiteAiQuestion->id);

            if ($question->reported_at) {
                return false;
            }

            $question->update([
                'report_reason' => $request->validated('reason'),
                'reported_at' => now(),
            ]);

            return true;
        });

        if ($wasReported) {
            User::query()->where('role', User::ROLE_ADMIN)->each(
                fn (User $admin) => Mail::to($admin)->send(new WebsiteAiQuestionReported($websiteAiQuestion->fresh()))
            );
        }

        return Redirect::route('admin.websites.show', [$website, 'assistant' => 'open'])
            ->with('status', $wasReported ? 'Thanks — this answer has been reported for investigation.' : 'This answer has already been reported.');
    }

    public function show(WebsiteAiQuestion $websiteAiQuestion): View
    {
        abort_unless($websiteAiQuestion->reported_at, 404);
        $websiteAiQuestion->load(['website.domains', 'user', 'creditedBy']);

        return view('admin.website-ai-question-report', compact('websiteAiQuestion'));
    }
}

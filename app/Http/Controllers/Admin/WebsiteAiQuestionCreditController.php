<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteAiQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WebsiteAiQuestionCreditController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, WebsiteAiQuestion $websiteAiQuestion): RedirectResponse
    {
        abort_unless($websiteAiQuestion->reported_at, 404);

        WebsiteAiQuestion::query()
            ->whereKey($websiteAiQuestion->getKey())
            ->whereNull('credited_at')
            ->update([
                'credited_at' => now(),
                'credited_by_user_id' => $request->user()->id,
            ]);

        return Redirect::route('admin.website-ai-question-reports.show', $websiteAiQuestion)
            ->with('status', 'The question has been returned to the user’s weekly allowance.');
    }
}

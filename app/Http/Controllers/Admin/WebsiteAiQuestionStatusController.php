<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteAiQuestionStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Website $website, WebsiteAiQuestion $websiteAiQuestion): JsonResponse
    {
        abort_unless($websiteAiQuestion->website_id === $website->id && $websiteAiQuestion->user_id === $request->user()->id && $website->isAccessibleBy($request->user()), 403);

        return response()->json([
            'id' => $websiteAiQuestion->id,
            'status' => $websiteAiQuestion->status,
            'answer' => $websiteAiQuestion->answer,
            'error' => $websiteAiQuestion->error,
        ]);
    }
}

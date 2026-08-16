<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteAiQuestionRequest;
use App\Jobs\ProcessWebsiteAiQuestion;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class WebsiteAiChatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreWebsiteAiQuestionRequest $request, Website $website): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $limit = (int) config('memberships.website_ai_questions_per_week', 25);
        $weekStartsAt = now()->startOfWeek();
        $lockKey = 'website-ai-question:'.$website->id.':'.$user->id.':'.$weekStartsAt->toDateString();

        $question = Cache::lock($lockKey, 10)->block(3, function () use ($request, $website, $user, $limit, $weekStartsAt): WebsiteAiQuestion {
            $used = WebsiteAiQuestion::query()
                ->whereBelongsTo($website)
                ->whereBelongsTo($user)
                ->where('created_at', '>=', $weekStartsAt)
                ->countsTowardsAllowance()
                ->count();

            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'question' => "You have used this website's {$limit} AI questions for this week. Your allowance resets on Monday.",
                ]);
            }

            return WebsiteAiQuestion::query()->create([
                'website_id' => $website->id,
                'user_id' => $user->id,
                'question' => $request->validated('question'),
            ]);
        });

        ProcessWebsiteAiQuestion::dispatch($question->id)->afterCommit();

        if ($request->expectsJson()) {
            return response()->json([
                'question' => ['id' => $question->id, 'question' => $question->question, 'status' => $question->status],
                'status_url' => route('admin.websites.assistant.questions.show', [$website, $question]),
            ], 202);
        }

        return Redirect::route('admin.websites.show', [$website, 'assistant' => 'open'])
            ->with('status', 'Your question is being answered in the background.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\WebsiteDataAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteAiQuestionRequest;
use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use App\Services\WebsiteAiContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Throwable;

class WebsiteAiChatController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreWebsiteAiQuestionRequest $request, Website $website, WebsiteAiContext $context): RedirectResponse
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

        try {
            $history = WebsiteAiQuestion::query()
                ->whereBelongsTo($website)
                ->whereBelongsTo($user)
                ->whereKeyNot($question->id)
                ->where('status', 'completed')
                ->latest()
                ->limit(5)
                ->get(['question', 'answer'])
                ->reverse()
                ->values()
                ->toArray();
            $prompt = (string) json_encode([
                'previous_conversation' => $history,
                'current_question' => $question->question,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $response = (new WebsiteDataAssistant($website->name, $context->for($website)))
                ->prompt($prompt, timeout: 60);
            $question->update([
                'answer' => $response['answer'],
                'status' => 'completed',
            ]);
        } catch (Throwable $exception) {
            Log::error('Website data assistant request failed.', [
                'website_ai_question_id' => $question->id,
                'website_id' => $website->id,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);
            report($exception);
            $question->update([
                'status' => 'failed',
                'error' => "The assistant could not answer this question. Reference: WAI-{$question->id}.",
            ]);

            return Redirect::route('admin.websites.show', [$website, 'assistant' => 'open'])
                ->with('error', 'The website assistant could not answer right now. This request still counts towards the weekly safety limit.');
        }

        return Redirect::route('admin.websites.show', [$website, 'assistant' => 'open']);
    }
}

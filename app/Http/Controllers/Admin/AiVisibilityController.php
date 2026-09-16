<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAiVisibilityPromptRequest;
use App\Http\Requests\UpdateAiVisibilitySettingsRequest;
use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilityResult;
use App\Models\AiVisibilitySetting;
use App\Models\Website;
use App\Services\AiVisibilityAnalyzer;
use App\Services\AiVisibilityPromptSuggestions;
use App\Services\AiVisibilityProviderRegistry;
use App\Services\AiVisibilityReport;
use App\Services\AiVisibilityScheduler;
use App\Services\AiVisibilitySetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AiVisibilityController extends Controller
{
    public function index(Request $request, Website $website, AiVisibilityReport $reports, AiVisibilityProviderRegistry $providers, AiVisibilityAnalyzer $analyzer, AiVisibilitySetup $setup, AiVisibilityPromptSuggestions $suggestions): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        $results = AiVisibilityResult::query()->where('website_id', $website->id)->where('status', 'completed');
        $provider = 'openai';
        $first = (clone $results)->when($provider, fn ($query) => $query->where('provider', $provider))->min('checked_at');
        $age = $first ? (int) Carbon::parse($first)->startOfDay()->diffInDays(today()) + 1 : 0;
        $ranges = $age < 30 ? ['all' => 'Since first check'] : [];
        foreach ([30 => '30 days', 90 => '3 months', 180 => '6 months', 365 => '12 months'] as $days => $label) {
            if ($age >= $days) {
                $ranges[$days] = $label;
            }
        }
        $range = $request->query('range', array_key_first($ranges));
        abort_unless(is_scalar($range), 404);
        $range = array_key_exists($range, $ranges) ? $range : array_key_first($ranges);
        $start = $range === 'all' ? ($first ? Carbon::parse($first)->startOfDay() : today()->subDays(6)) : today()->subDays((int) $range - 1);
        $settings = $setup->settings($website);
        $prompts = AiVisibilityPrompt::query()->where('website_id', $website->id)->with(['results' => fn ($query) => $query->where('provider', 'openai')->whereIn('id', AiVisibilityResult::query()->selectRaw('MAX(id)')->where('website_id', $website->id)->groupBy('ai_visibility_prompt_id', 'provider'))->latest('id')])->orderByDesc('active')->orderByDesc('id')->paginate(20)->withQueryString();
        $ideas = $suggestions->forWebsite($website, $settings);
        $activeCount = AiVisibilityPrompt::query()->where('website_id', $website->id)->where('active', true)->count();

        return view('admin.ai-visibility.index', [
            'website' => $website, 'settings' => $settings, 'identity' => $analyzer->identity($website, $settings), 'openaiAvailable' => $providers->availability()['openai'],
            'suggestions' => array_slice($ideas, 0, max(0, (int) config('ai_visibility.max_active_prompts') - $activeCount)), 'activePromptCount' => $activeCount,
            'report' => $reports->forPeriod($website, $start, now()->endOfDay(), $provider), 'trend' => $reports->trend($website, $start, now()->endOfDay(), $provider),
            'prompts' => $prompts, 'ranges' => $ranges, 'range' => $range, 'provider' => $provider, 'canManage' => $website->isManageableBy($request->user()),
            'keywords' => $website->seoTargetKeywords()->whereNull('archived_at')->orderBy('term')->get(['id', 'term']),
        ]);
    }

    public function show(Request $request, Website $website, AiVisibilityPrompt $prompt, AiVisibilityReport $reports): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        $this->assertNested($website, $prompt);
        $results = $prompt->results()->where('provider', 'openai')->latest('id')->paginate(20);
        $latest = $prompt->results()->where('provider', 'openai')->latest('id')->limit(1)->get();

        return view('admin.ai-visibility.show', ['website' => $website, 'prompt' => $prompt, 'results' => $results, 'latest' => $latest, 'trend' => $reports->trend($website, today()->subDays(364), now()->endOfDay(), provider: 'openai', promptId: $prompt->id), 'canManage' => $website->isManageableBy($request->user()), 'keywords' => $website->seoTargetKeywords()->whereNull('archived_at')->orderBy('term')->get(['id', 'term'])]);
    }

    public function store(SaveAiVisibilityPromptRequest $request, Website $website): RedirectResponse
    {
        $this->savePrompt($website, new AiVisibilityPrompt(['website_id' => $website->id]), $request->validated());

        $remaining = collect($request->session()->get('aiPromptSuggestions', []))->reject(fn ($idea) => AiVisibilityPrompt::fingerprint($idea['prompt']) === AiVisibilityPrompt::fingerprint($request->validated('prompt')))->values()->all();

        $response = $this->redirect($website, 'Prompt saved. Use Check due prompts to queue eligible checks.');

        return $request->session()->has('aiPromptSuggestions') ? $response->with('aiPromptSuggestions', $remaining) : $response;
    }

    public function update(SaveAiVisibilityPromptRequest $request, Website $website, AiVisibilityPrompt $prompt): RedirectResponse
    {
        $this->assertNested($website, $prompt);
        $this->savePrompt($website, $prompt, $request->validated());

        return $this->redirect($website, 'Prompt updated. Previous results retain their original prompt.');
    }

    public function destroy(Request $request, Website $website, AiVisibilityPrompt $prompt): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $this->assertNested($website, $prompt);
        $prompt->delete();

        return $this->redirect($website, 'Prompt deleted. Historical evidence is retained.');
    }

    public function settings(UpdateAiVisibilitySettingsRequest $request, Website $website, AiVisibilityPromptSuggestions $suggestions, AiVisibilityScheduler $scheduler): RedirectResponse
    {
        $started = DB::transaction(function () use ($request, $website, $suggestions): bool {
            Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            $previous = AiVisibilitySetting::query()->where('website_id', $website->id)->first();
            $started = $request->boolean('enabled') && ! $previous?->enabled;
            $settings = AiVisibilitySetting::query()->updateOrCreate(['website_id' => $website->id], $request->validated());
            if ($settings->enabled && ! AiVisibilityPrompt::query()->where('website_id', $website->id)->where('active', true)->exists()) {
                $ideas = array_slice($suggestions->forWebsite($website, $settings), 0, max(0, (int) config('ai_visibility.max_active_prompts')));
                if ($ideas === []) {
                    throw ValidationException::withMessages(['services' => 'We need one service or customer question to get started. Add a service below, add a tracked keyword, or reactivate an existing question.']);
                }
                foreach ($ideas as $idea) {
                    $this->savePrompt($website, new AiVisibilityPrompt(['website_id' => $website->id]), [...$idea, 'active' => true, 'priority' => 'normal']);
                }
                $started = true;
            }

            return $started;
        });

        if ($started) {
            $count = $scheduler->queue($website);

            return $this->redirect($website, $count > 0 ? 'AI tracking is on. '.$count.' first checks queued; results will appear here when ready.' : 'AI tracking is on. No checks are due or this website is not currently eligible for checks.');
        }

        return $this->redirect($website, $request->boolean('enabled') ? 'Tracking settings saved.' : 'AI tracking is paused. Your questions and results have been kept.');
    }

    public function suggestions(Request $request, Website $website, AiVisibilityPromptSuggestions $suggestions): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        return $this->redirect($website, 'Suggested questions are ready below. You can use them when turning on tracking.')->with('aiPromptSuggestions', $suggestions->forWebsite($website));
    }

    public function check(Request $request, Website $website, AiVisibilityScheduler $scheduler, ?AiVisibilityPrompt $prompt = null): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        if ($prompt) {
            $this->assertNested($website, $prompt);
        }
        $count = $scheduler->queue($website, $prompt);

        return $this->redirect($website, $count > 0 ? $count.' AI checks queued.' : 'No checks are due. Turn on tracking and make sure you have an active question.');
    }

    /** @param array<string, mixed> $data */
    private function savePrompt(Website $website, AiVisibilityPrompt $prompt, array $data): void
    {
        DB::transaction(function () use ($website, $prompt, $data): void {
            Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            $others = AiVisibilityPrompt::withTrashed()->where('website_id', $website->id)->when($prompt->exists, fn ($query) => $query->whereKeyNot($prompt->id));
            $duplicate = (clone $others)->where('fingerprint', AiVisibilityPrompt::fingerprint($data['prompt']))->first();
            if ($duplicate) {
                if (! $prompt->exists && $duplicate->trashed()) {
                    $prompt = $duplicate;
                    $others->whereKeyNot($prompt->id);
                } else {
                    throw ValidationException::withMessages(['prompt' => 'This prompt already exists. Edit its existing record or choose a different question.']);
                }
            }
            if ($data['active'] && (clone $others)->whereNull('deleted_at')->where('active', true)->count() >= config('ai_visibility.max_active_prompts')) {
                throw ValidationException::withMessages(['prompt' => 'Disable a prompt first. This site can track up to '.config('ai_visibility.max_active_prompts').' active prompts.']);
            }
            $prompt->fill($data);
            if ($prompt->trashed()) {
                $prompt->restore();
            } else {
                $prompt->save();
            }
        });
    }

    private function assertNested(Website $website, AiVisibilityPrompt $prompt): void
    {
        abort_unless($prompt->website_id === $website->id, 404);
    }

    private function redirect(Website $website, string $message): RedirectResponse
    {
        return redirect()->route('admin.ai-visibility.index', $website)->with('status', $message);
    }
}

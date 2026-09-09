<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeoTargetKeywordRequest;
use App\Http\Requests\UpdateSeoTargetKeywordRequest;
use App\Jobs\CheckSeoTargetKeywordRanking;
use App\Models\SeoTargetKeyword;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeoTargetKeywordController extends Controller
{
    public function store(StoreSeoTargetKeywordRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($website, $data): void {
            Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            if ($website->seoTargetKeywords()->whereNull('archived_at')->count() >= 20) {
                throw ValidationException::withMessages(['term' => 'Archive a target before adding another. A website can track up to 20 active terms.']);
            }
            if ($website->seoTargetKeywords()->where('normalized_term', SeoTargetKeyword::normalize($data['term']))->exists()) {
                throw ValidationException::withMessages(['term' => 'This target term already exists. Restore or edit its existing record.']);
            }
            $website->seoTargetKeywords()->create($data);
        });

        return $this->redirect($website, 'Target keyword added.');
    }

    public function update(UpdateSeoTargetKeywordRequest $request, Website $website, SeoTargetKeyword $seoTargetKeyword): RedirectResponse
    {
        $this->assertNested($website, $seoTargetKeyword);
        $normalized = SeoTargetKeyword::normalize($request->string('term')->toString());
        if ($website->seoTargetKeywords()->where('normalized_term', $normalized)->whereKeyNot($seoTargetKeyword->id)->exists()) {
            throw ValidationException::withMessages(['term' => 'This target term already exists.']);
        }
        $seoTargetKeyword->update($request->validated());

        return $this->redirect($website, 'Target keyword updated.');
    }

    public function archive(Request $request, Website $website, SeoTargetKeyword $seoTargetKeyword): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $this->assertNested($website, $seoTargetKeyword);
        $seoTargetKeyword->update(['archived_at' => now()]);

        return $this->redirect($website, 'Target keyword archived.');
    }

    public function restore(Request $request, Website $website, SeoTargetKeyword $seoTargetKeyword): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $this->assertNested($website, $seoTargetKeyword);
        DB::transaction(function () use ($website, $seoTargetKeyword): void {
            Website::query()->whereKey($website)->lockForUpdate()->firstOrFail();
            if ($website->seoTargetKeywords()->whereNull('archived_at')->count() >= 20) {
                throw ValidationException::withMessages(['term' => 'Archive a target before restoring this one. A website can track up to 20 active terms.']);
            }
            $seoTargetKeyword->update(['archived_at' => null]);
        });

        return $this->redirect($website, 'Target keyword restored.');
    }

    public function check(Request $request, Website $website, SeoTargetKeyword $seoTargetKeyword): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $this->assertNested($website, $seoTargetKeyword);
        abort_if($seoTargetKeyword->archived_at !== null, 422);
        CheckSeoTargetKeywordRanking::dispatch($seoTargetKeyword);

        return $this->redirect($website, 'Ranking check queued.');
    }

    private function assertNested(Website $website, SeoTargetKeyword $keyword): void
    {
        abort_unless($keyword->website_id === $website->id, 404);
    }

    private function redirect(Website $website, string $message): RedirectResponse
    {
        return redirect()->route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'targets'])->with('status', $message);
    }
}

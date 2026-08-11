<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfileRecommendation;
use App\Models\Website;
use App\Services\BusinessProfileClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BusinessProfileRecommendationController extends Controller
{
    public function update(Request $request, Website $website, BusinessProfileRecommendation $recommendation, BusinessProfileClient $client): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($recommendation->audit->connection->website_id === $website->id, 404);
        abort_unless($recommendation->status === BusinessProfileRecommendation::STATUS_PENDING && $recommendation->field_mask && $recommendation->proposed_value, 422);
        $recommendation->update(['status' => BusinessProfileRecommendation::STATUS_APPLYING, 'approved_by' => $request->user()->id, 'approved_at' => now(), 'error' => null]);
        try {
            $client->updateLocation($recommendation->audit->connection, $recommendation->field_mask, $recommendation->proposed_value);
            $recommendation->update(['status' => BusinessProfileRecommendation::STATUS_APPLIED, 'applied_at' => now()]);
        } catch (Throwable $exception) {
            $recommendation->update(['status' => BusinessProfileRecommendation::STATUS_FAILED, 'error' => $exception->getMessage()]);
            throw $exception;
        }

        return back()->with('status', 'Business Profile recommendation applied.');
    }

    public function destroy(Request $request, Website $website, BusinessProfileRecommendation $recommendation): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($recommendation->audit->connection->website_id === $website->id, 404);
        $recommendation->update(['status' => BusinessProfileRecommendation::STATUS_DISMISSED]);

        return back()->with('status', 'Recommendation dismissed.');
    }
}

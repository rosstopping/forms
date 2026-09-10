<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewInvitationRequest;
use App\Http\Requests\UpdateReviewLinkRequest;
use App\Jobs\SendReviewInvitation;
use App\Models\FormSubmission;
use App\Services\ReviewInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormSubmissionReviewInvitationController extends Controller
{
    public function updateLink(UpdateReviewLinkRequest $request, FormSubmission $formSubmission): RedirectResponse
    {
        $formSubmission->website->update($request->validated());

        return to_route('admin.form-submissions.show', $formSubmission)->with('status', 'Website review link saved.');
    }

    public function store(StoreReviewInvitationRequest $request, FormSubmission $formSubmission, ReviewInvitationService $reviews): RedirectResponse
    {
        $created = DB::transaction(function () use ($request, $formSubmission, $reviews): bool {
            $lead = FormSubmission::query()->lockForUpdate()->findOrFail($formSubmission->id);
            if ($lead->reviewInvitation()->exists()) {
                return false;
            }
            if ($reason = $reviews->unavailableReason($lead, $request->user())) {
                throw ValidationException::withMessages(['review_invitation' => $reason]);
            }
            $snapshot = $reviews->snapshot($lead);
            if (! hash_equals($reviews->fingerprint($snapshot), $request->validated('preview_hash'))) {
                throw ValidationException::withMessages(['review_invitation' => 'The invitation details changed. Refresh this page and review them before sending.']);
            }
            $invitation = $lead->reviewInvitation()->create([
                ...$snapshot, 'website_id' => $lead->website_id, 'requested_by' => $request->user()->id,
            ]);
            $lead->recordActivity('review_invitation_queued', 'Review invitation queued.', $request->user(), ['review_invitation_id' => $invitation->id, 'recipient' => $snapshot['recipient']]);
            SendReviewInvitation::dispatch($invitation->id)->afterCommit();

            return true;
        });

        return to_route('admin.form-submissions.show', $formSubmission)->with('status', $created ? 'Review invitation queued.' : 'A review invitation already exists for this lead.');
    }
}

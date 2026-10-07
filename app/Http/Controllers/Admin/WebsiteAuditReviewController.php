<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendWebsiteAuditReviewRequest;
use App\Http\Requests\UpdateWebsiteAuditLeadRequest;
use App\Mail\WebsiteAuditPersonalReview;
use App\Models\WebsiteAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WebsiteAuditReviewController extends Controller
{
    public function send(SendWebsiteAuditReviewRequest $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        DB::transaction(function () use ($request, $websiteAudit): void {
            $audit = WebsiteAudit::query()->lockForUpdate()->findOrFail($websiteAudit->id);
            abort_unless($audit->personal_review_requested_at && $audit->email && $audit->status === WebsiteAudit::STATUS_COMPLETED, 422);
            if ($audit->personal_review_queued_at !== null) {
                return;
            }
            $audit->update([
                'personal_review' => $request->validated('priorities'),
                'personal_review_queued_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);
            Mail::to($audit->email)->queue(new WebsiteAuditPersonalReview($audit));
        });

        return back()->with('status', 'Personal recommendations queued.');
    }

    public function update(UpdateWebsiteAuditLeadRequest $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        abort_unless($websiteAudit->personal_review_requested_at && $websiteAudit->email, 422);
        $column = match ($request->validated('stage')) {
            'replied' => 'lead_replied_at',
            'call_booked' => 'lead_call_booked_at',
            'converted' => 'lead_converted_at',
        };
        if ($websiteAudit->{$column} === null) {
            $websiteAudit->update([$column => now()]);
        }

        return back()->with('status', 'Lead updated.');
    }
}

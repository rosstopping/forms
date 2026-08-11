<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateFormSubmissionsRequest;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class BulkFormSubmissionController extends Controller
{
    public function __invoke(BulkUpdateFormSubmissionsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $submissionIds = collect($data['submission_ids'] ?? [])->map(static fn (mixed $id): int => (int) $id)->unique()->values();
        $authorizedQuery = FormSubmission::query();

        if ($data['selection_scope'] === 'page') {
            $authorizedQuery->whereKey($submissionIds);
        } else {
            $authorizedQuery->filtered([
                'search' => $data['search'] ?? null,
                'status' => $data['filter_status'] ?? null,
                'website_id' => $data['website_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'follow_up' => $data['follow_up'] ?? null,
                'spam' => $data['spam'] ?? 'exclude',
            ]);
        }

        if (! $request->user()->isAdmin()) {
            $authorizedQuery->whereHas('website', fn ($query) => $query->manageableBy($request->user()));
        }

        if ($data['selection_scope'] === 'page') {
            abort_unless((clone $authorizedQuery)->count() === $submissionIds->count(), 403);
        } else {
            abort_if((clone $authorizedQuery)->doesntExist(), 422, 'No leads match this selection.');
        }

        DB::transaction(function () use ($authorizedQuery, $data, $request): void {
            match ($data['action']) {
                'update_status' => $authorizedQuery->lazyById()->each(function (FormSubmission $submission) use ($data, $request): void {
                    $submission->update(['status' => $data['status']]);
                    $submission->recordActivity('status_changed', 'Status changed to '.$submission->resolvedStatusLabel().'.', $request->user());
                }),
                'mark_spam' => $authorizedQuery->lazyById()->each(function (FormSubmission $submission) use ($request): void {
                    $submission->update(['is_spam' => true]);
                    $submission->recordActivity('marked_spam', 'Lead marked as spam.', $request->user());
                }),
                'delete' => $authorizedQuery->delete(),
            };
        });

        $message = match ($data['action']) {
            'update_status' => 'Selected leads updated.',
            'mark_spam' => 'Selected leads marked as spam.',
            'delete' => 'Selected leads deleted.',
        };

        return back()->with('status', $message);
    }
}

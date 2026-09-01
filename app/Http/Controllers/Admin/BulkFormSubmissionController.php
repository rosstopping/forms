<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateFormSubmissionsRequest;
use App\Mail\FormSubmissionReceived;
use App\Models\FormSubmission;
use App\Services\FormSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Throwable;

class BulkFormSubmissionController extends Controller
{
    public function __invoke(BulkUpdateFormSubmissionsRequest $request, FormSettingsResolver $formSettingsResolver): RedirectResponse
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

        if ($data['action'] === 'resend_notification') {
            [$sentCount, $skippedCount] = $this->resendNotifications($authorizedQuery, $request, $formSettingsResolver);
            $message = $sentCount.' email '.str('notification')->plural($sentCount).' resent.';

            if ($skippedCount > 0) {
                $message .= ' '.$skippedCount.' '.str('lead')->plural($skippedCount).' skipped because they were spam, had no recipients, or could not be sent.';
            }

            return back()->with('status', $message);
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

    /** @return array{int, int} */
    private function resendNotifications($authorizedQuery, BulkUpdateFormSubmissionsRequest $request, FormSettingsResolver $formSettingsResolver): array
    {
        $sentCount = 0;
        $skippedCount = 0;

        (clone $authorizedQuery)
            ->with(['form.website.domains'])
            ->lazyById()
            ->each(function (FormSubmission $submission) use ($request, $formSettingsResolver, &$sentCount, &$skippedCount): void {
                $form = $submission->form;
                $recipients = $form ? $formSettingsResolver->resolveEmailRecipients($form) : [];

                if ($submission->is_spam || $recipients === []) {
                    $skippedCount++;

                    return;
                }

                try {
                    Mail::to($recipients)->send(new FormSubmissionReceived($submission));
                    $submission->update([
                        'email_sent_at' => now(),
                        'email_failed_at' => null,
                        'email_error' => null,
                    ]);
                    $submission->recordActivity('team_email_resent', 'New lead notification resent to the team in bulk.', $request->user());
                    $sentCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $submission->update([
                        'email_failed_at' => now(),
                        'email_error' => $exception->getMessage(),
                    ]);
                    $submission->recordActivity('team_email_resend_failed', 'New lead notification could not be resent in bulk.', $request->user());
                    $skippedCount++;
                }
            });

        return [$sentCount, $skippedCount];
    }
}

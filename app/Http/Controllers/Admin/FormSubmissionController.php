<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFormSubmissionRequest;
use App\Mail\FormSubmissionReceived;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Services\FormSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Throwable;

class FormSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $filterKeys = ['search', 'status', 'website_id', 'assigned_to', 'follow_up', 'spam'];
        $sessionKey = 'admin.lead_filters.'.$request->user()->id;

        if ($request->boolean('reset_filters')) {
            $request->session()->forget($sessionKey);
        } elseif ($request->hasAny($filterKeys)) {
            $request->session()->put($sessionKey, array_filter(
                $request->only($filterKeys),
                static fn (mixed $value): bool => $value !== null && $value !== '',
            ));
        } else {
            $request->merge($request->session()->get($sessionKey, []));
        }

        $query = FormSubmission::query();

        if (! $request->user()?->isAdmin()) {
            $query->whereHas('website', fn ($query) => $query->accessibleTo($request->user()));
        }

        $summary = (clone $query)->where('is_spam', false)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $followUpSummary = [
            'overdue' => (clone $query)->filtered(['follow_up' => 'overdue', 'spam' => 'exclude'])->count(),
            'today' => (clone $query)->filtered(['follow_up' => 'today', 'spam' => 'exclude'])->count(),
        ];

        $query->filtered($request->only($filterKeys));

        $bulkSelectableCount = (clone $query)
            ->whereHas('website', fn ($query) => $query->manageableBy($request->user()))
            ->count();

        $submissions = $query
            ->with(['website', 'form', 'assignee'])
            ->latest('created_at')
            ->paginate(20)->withQueryString();

        $websites = Website::query()->when(! $request->user()?->isAdmin(), fn ($query) => $query->accessibleTo($request->user()))->orderBy('name')->get(['id', 'name']);
        $manageableWebsiteIds = Website::query()->manageableBy($request->user())->pluck('id');
        $bulkPageSelectableCount = $submissions->getCollection()->whereIn('website_id', $manageableWebsiteIds)->count();
        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);

        return view('admin.form-submissions.index', compact('submissions', 'summary', 'followUpSummary', 'websites', 'manageableWebsiteIds', 'bulkSelectableCount', 'bulkPageSelectableCount', 'users'));
    }

    public function show(Request $request, FormSubmission $formSubmission)
    {
        abort_unless($formSubmission->website?->isAccessibleBy($request->user()), 403);

        $formSubmission->load(['website', 'form', 'assignee', 'activities.user']);

        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);
        $canManage = $formSubmission->website?->isManageableBy($request->user()) === true;

        return view('admin.form-submissions.show', compact('formSubmission', 'users', 'canManage'));
    }

    public function update(UpdateFormSubmissionRequest $request, FormSubmission $formSubmission)
    {
        $data = $request->safe()->except('return_to');

        if (! $request->user()?->isAdmin() && filled($data['assigned_to'] ?? null) && (int) $data['assigned_to'] !== $request->user()->id) {
            abort(403);
        }

        $formSubmission->fill($data)->save();

        if ($formSubmission->wasChanged('status')) {
            $formSubmission->recordActivity('status_changed', 'Status changed to '.$formSubmission->resolvedStatusLabel().'.', $request->user());
        }

        if ($formSubmission->wasChanged('assigned_to')) {
            $assigneeName = $formSubmission->assigned_to ? User::query()->whereKey($formSubmission->assigned_to)->value('name') : null;
            $formSubmission->recordActivity('assignment_changed', $assigneeName ? 'Assigned to '.$assigneeName.'.' : 'Lead unassigned.', $request->user());
        }

        if ($formSubmission->wasChanged('follow_up_at')) {
            $description = $formSubmission->follow_up_at
                ? 'Follow-up scheduled for '.$formSubmission->follow_up_at->format('j M Y, H:i').'.'
                : 'Follow-up reminder cleared.';
            $formSubmission->recordActivity('follow_up_changed', $description, $request->user());
        }

        if ($formSubmission->wasChanged('notes')) {
            $formSubmission->recordActivity('notes_updated', 'Lead notes updated.', $request->user());
        }

        $redirectTo = $request->input('return_to');

        if (is_string($redirectTo) && Str::startsWith($redirectTo, '/admin')) {
            return Redirect::to($redirectTo)->with('status', 'Lead updated.');
        }

        $referer = $request->header('referer');

        if ($referer && str_contains($referer, '/admin')) {
            return Redirect::to($referer)->with('status', 'Lead updated.');
        }

        return Redirect::route('admin.form-submissions.show', $formSubmission)->with('status', 'Lead updated.');
    }

    public function markSpam(Request $request, FormSubmission $formSubmission): RedirectResponse
    {
        abort_unless($formSubmission->website?->isManageableBy($request->user()), 403);

        $formSubmission->update(['is_spam' => true]);
        $formSubmission->recordActivity('marked_spam', 'Lead marked as spam.', $request->user());

        return Redirect::route('admin.form-submissions.index')->with('status', 'Lead marked as spam.');
    }

    public function resendNotification(Request $request, FormSubmission $formSubmission, FormSettingsResolver $formSettingsResolver): RedirectResponse
    {
        abort_unless($formSubmission->website?->isManageableBy($request->user()), 403);
        abort_if($formSubmission->is_spam, 422, 'Spam submissions cannot be emailed.');

        $form = $formSubmission->form;
        $recipients = $form ? $formSettingsResolver->resolveEmailRecipients($form) : [];

        if ($recipients === []) {
            return Redirect::route('admin.form-submissions.show', $formSubmission)
                ->withErrors(['email_notification' => 'This form does not have any notification recipients configured.']);
        }

        try {
            Mail::to($recipients)->send(new FormSubmissionReceived($formSubmission));
            $formSubmission->update([
                'email_sent_at' => now(),
                'email_failed_at' => null,
                'email_error' => null,
            ]);
            $formSubmission->recordActivity('team_email_resent', 'New lead notification resent to the team.', $request->user());
        } catch (Throwable $exception) {
            report($exception);
            $formSubmission->update([
                'email_failed_at' => now(),
                'email_error' => $exception->getMessage(),
            ]);
            $formSubmission->recordActivity('team_email_resend_failed', 'New lead notification could not be resent.', $request->user());

            return Redirect::route('admin.form-submissions.show', $formSubmission)
                ->withErrors(['email_notification' => 'The notification could not be sent. Please try again.']);
        }

        return Redirect::route('admin.form-submissions.show', $formSubmission)
            ->with('status', 'Email notification resent.');
    }

    public function destroy(Request $request, FormSubmission $formSubmission): RedirectResponse
    {
        abort_unless($formSubmission->website?->isManageableBy($request->user()), 403);

        $formSubmission->delete();

        return Redirect::route('admin.form-submissions.index')->with('status', 'Lead deleted.');
    }
}

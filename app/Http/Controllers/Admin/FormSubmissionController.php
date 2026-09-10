<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterFormSubmissionsRequest;
use App\Http\Requests\StoreManualLeadRequest;
use App\Http\Requests\UpdateFormSubmissionRequest;
use App\Mail\FormSubmissionReceived;
use App\Models\FormSubmission;
use App\Models\LeadTag;
use App\Models\User;
use App\Models\Website;
use App\Services\FormSettingsResolver;
use App\Services\ReviewInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FormSubmissionController extends Controller
{
    public function index(FilterFormSubmissionsRequest $request): View
    {
        $currentWebsite = $request->attributes->get('currentWebsite');
        abort_unless($currentWebsite instanceof Website && $currentWebsite->isAccessibleBy($request->user()), 404);
        $filterKeys = ['search', 'status', 'assigned_to', 'follow_up', 'spam', 'tag_id'];
        $sessionKey = 'admin.lead_filters.'.$request->user()->id.'.'.$currentWebsite->id;

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

        $query = FormSubmission::query()->whereBelongsTo($currentWebsite);

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
            ->with(['website', 'form', 'assignee', 'tags'])
            ->latest('created_at')
            ->paginate(20)->withQueryString();

        $manageableWebsiteIds = $currentWebsite->isManageableBy($request->user()) ? collect([$currentWebsite->id]) : collect();
        $bulkPageSelectableCount = $submissions->getCollection()->whereIn('website_id', $manageableWebsiteIds)->count();
        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);

        $leadTags = LeadTag::query()->whereBelongsTo($currentWebsite)->orderBy('name')->get();

        return view('admin.form-submissions.index', compact('submissions', 'summary', 'followUpSummary', 'manageableWebsiteIds', 'bulkSelectableCount', 'bulkPageSelectableCount', 'users', 'leadTags'));
    }

    public function create(Request $request): View
    {
        $website = $request->attributes->get('currentWebsite');
        abort_unless($website instanceof Website && $website->isManageableBy($request->user()), 403);

        return view('admin.form-submissions.create', compact('website'));
    }

    public function store(StoreManualLeadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $website = $request->attributes->get('currentWebsite');
        $lead = DB::transaction(function () use ($request, $data, $website): FormSubmission {
            $lead = FormSubmission::create([
                'website_id' => $website->id,
                'form_id' => null,
                'is_manual' => true,
                'is_spam' => false,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'follow_up_at' => $data['follow_up_at'] ?? null,
                'data' => array_intersect_key($data, array_flip(['name', 'email', 'phone', 'message'])),
            ]);
            $lead->recordActivity('created', 'Lead added manually.', $request->user());
            if ($lead->follow_up_at) {
                $lead->followUpReminders()->create(['due_at' => $lead->follow_up_at]);
                $lead->recordActivity('follow_up_changed', 'Follow-up scheduled for '.$lead->follow_up_at->format('j M Y, H:i').'.', $request->user());
            }

            return $lead;
        });

        return to_route('admin.form-submissions.show', $lead)->with('status', 'Lead added.');
    }

    public function show(Request $request, FormSubmission $formSubmission): View
    {
        abort_unless($formSubmission->website?->isAccessibleBy($request->user()), 403);

        $formSubmission->load(['website', 'form', 'assignee', 'activities.user', 'tags', 'reviewInvitation.requester']);

        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);
        $canManage = $formSubmission->website?->isManageableBy($request->user()) === true;

        $leadTags = LeadTag::query()->where('website_id', $formSubmission->website_id)->orderBy('name')->get();

        $reviews = app(ReviewInvitationService::class);
        $reviewUnavailableReason = $reviews->unavailableReason($formSubmission, $request->user());
        $reviewPreview = $reviews->snapshot($formSubmission);
        $reviewPreviewHash = $reviews->fingerprint($reviewPreview);

        return view('admin.form-submissions.show', compact('formSubmission', 'users', 'canManage', 'leadTags', 'reviewUnavailableReason', 'reviewPreview', 'reviewPreviewHash'));
    }

    public function update(UpdateFormSubmissionRequest $request, FormSubmission $formSubmission): RedirectResponse
    {
        $data = $request->safe()->except(['return_to', 'tag_ids', 'new_tag', 'tags_present', 'name', 'email', 'phone', 'message']);

        if (! $request->user()?->isAdmin() && filled($data['assigned_to'] ?? null) && (int) $data['assigned_to'] !== $request->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($formSubmission, $data, $request): void {
            $formSubmission = FormSubmission::query()->lockForUpdate()->findOrFail($formSubmission->id);
            if ($formSubmission->is_manual && $request->hasAny(['name', 'email', 'phone', 'message'])) {
                $data['data'] = [...($formSubmission->data ?? []), ...$request->safe()->only(['name', 'email', 'phone', 'message'])];
            }
            $formSubmission->fill($data)->save();
            if ($formSubmission->wasChanged('data')) {
                $formSubmission->recordActivity('contact_details_updated', 'Manual lead contact details updated.', $request->user());
            }

            if ($request->boolean('tags_present') || $request->has('tag_ids') || filled($request->validated('new_tag'))) {
                $tagIds = array_map(intval(...), $request->validated('tag_ids', $request->boolean('tags_present') ? [] : $formSubmission->tags()->allRelatedIds()->all()));
                if (filled($request->validated('new_tag'))) {
                    $tag = LeadTag::query()->firstOrCreate(
                        ['website_id' => $formSubmission->website_id, 'normalized_name' => Str::lower($request->validated('new_tag'))],
                        ['name' => $request->validated('new_tag')],
                    );
                    $tagIds[] = $tag->id;
                }
                if (count(array_unique($tagIds)) > 20) {
                    throw ValidationException::withMessages(['tag_ids' => 'Choose up to 20 tags per lead.']);
                }
                $changes = $formSubmission->tags()->sync(array_unique($tagIds));
                if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                    $formSubmission->recordActivity('tags_updated', 'Lead tags updated.', $request->user(), [
                        'tags' => $formSubmission->tags()->pluck('name')->all(),
                    ]);
                }
            }

            if ($formSubmission->wasChanged('status')) {
                $formSubmission->recordActivity('status_changed', 'Status changed to '.$formSubmission->resolvedStatusLabel().'.', $request->user());
            }

            if ($formSubmission->wasChanged('assigned_to')) {
                $assigneeName = $formSubmission->assigned_to ? User::query()->whereKey($formSubmission->assigned_to)->value('name') : null;
                $formSubmission->recordActivity('assignment_changed', $assigneeName ? 'Assigned to '.$assigneeName.'.' : 'Lead unassigned.', $request->user());
            }

            if ($formSubmission->wasChanged('follow_up_at')) {
                foreach ($formSubmission->followUpReminders()->where('status', 'pending')->lockForUpdate()->get() as $reminder) {
                    $reminder->cancel('Follow-up date changed or cleared.');
                }
                if ($formSubmission->follow_up_at !== null) {
                    $formSubmission->followUpReminders()->create(['due_at' => $formSubmission->follow_up_at]);
                }

                $description = $formSubmission->follow_up_at
                    ? 'Follow-up scheduled for '.$formSubmission->follow_up_at->format('j M Y, H:i').'.'
                    : 'Follow-up reminder cleared.';
                $formSubmission->recordActivity('follow_up_changed', $description, $request->user());
            }

            if ($formSubmission->wasChanged('notes')) {
                $formSubmission->recordActivity('notes_updated', 'Lead notes updated.', $request->user());
            }
        });

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
        abort_if($formSubmission->is_manual, 422, 'Manual leads do not have a form notification to resend.');
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

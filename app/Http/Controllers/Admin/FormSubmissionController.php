<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFormSubmissionRequest;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class FormSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $filterKeys = ['search', 'status', 'website_id', 'assigned_to', 'spam'];
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

        $query->when($request->input('spam', 'exclude') === 'exclude', fn ($query) => $query->where('is_spam', false))
            ->when($request->input('spam') === 'only', fn ($query) => $query->where('is_spam', true))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('website_id'), fn ($query) => $query->where('website_id', $request->integer('website_id')))
            ->when($request->filled('assigned_to'), fn ($query) => $request->input('assigned_to') === 'unassigned' ? $query->whereNull('assigned_to') : $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.Str::limit($request->string('search')->trim(), 100, '').'%';
                $query->where(fn ($query) => $query->where('source_domain', 'like', $search)->orWhere('source_url', 'like', $search)->orWhere('data', 'like', $search));
            });

        $submissions = $query
            ->with(['website', 'form', 'assignee'])
            ->latest('created_at')
            ->paginate(20)->withQueryString();

        $websites = Website::query()->when(! $request->user()?->isAdmin(), fn ($query) => $query->accessibleTo($request->user()))->orderBy('name')->get(['id', 'name']);
        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);

        return view('admin.form-submissions.index', compact('submissions', 'summary', 'websites', 'users'));
    }

    public function show(Request $request, FormSubmission $formSubmission)
    {
        abort_unless($formSubmission->website?->isAccessibleBy($request->user()), 403);

        $formSubmission->load(['website', 'form', 'assignee']);

        $users = $request->user()?->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name']) : collect([$request->user()]);

        return view('admin.form-submissions.show', compact('formSubmission', 'users'));
    }

    public function update(UpdateFormSubmissionRequest $request, FormSubmission $formSubmission)
    {
        $data = $request->safe()->except('return_to');

        if (! $request->user()?->isAdmin() && filled($data['assigned_to'] ?? null) && (int) $data['assigned_to'] !== $request->user()->id) {
            abort(403);
        }

        $formSubmission->fill($data)->save();

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
}

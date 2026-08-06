<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class FormSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $query = FormSubmission::query();

        if (! $request->user()?->isAdmin()) {
            $query->whereHas('website', fn ($query) => $query->where('user_id', $request->user()->id));
        }

        $submissions = $query
            ->with(['website', 'form'])
            ->latest('created_at')
            ->paginate(20);

        return view('admin.form-submissions.index', compact('submissions'));
    }

    public function show(FormSubmission $formSubmission)
    {
        abort_unless(Auth::user()?->isAdmin() || $formSubmission->website?->user_id === Auth::id(), 403);

        $formSubmission->load(['website', 'form', 'assignee']);

        return view('admin.form-submissions.show', compact('formSubmission'));
    }

    public function update(Request $request, FormSubmission $formSubmission)
    {
        abort_unless(Auth::user()?->isAdmin() || $formSubmission->website?->user_id === Auth::id(), 403);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:new,contacted,qualified,won,lost'],
            'notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $formSubmission->fill($data)->save();

        $redirectTo = $request->input('return_to');

        if ($redirectTo) {
            return Redirect::to($redirectTo)->with('status', 'Lead updated.');
        }

        $referer = $request->header('referer');

        if ($referer && str_contains($referer, '/admin')) {
            return Redirect::to($referer)->with('status', 'Lead updated.');
        }

        return Redirect::route('admin.form-submissions.show', $formSubmission)->with('status', 'Lead updated.');
    }
}

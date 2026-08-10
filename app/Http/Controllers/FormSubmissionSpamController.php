<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormSubmissionSpamController extends Controller
{
    public function show(FormSubmission $formSubmission): View
    {
        return view('form-submissions.confirm-spam', compact('formSubmission'));
    }

    public function store(Request $request, FormSubmission $formSubmission): RedirectResponse
    {
        $formSubmission->update(['is_spam' => true]);

        return redirect()->to($request->fullUrl())->with('status', 'This submission has been marked as spam.');
    }
}

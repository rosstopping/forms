<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $submissions = FormSubmission::query()
            ->with(['website', 'form'])
            ->latest('created_at')
            ->paginate(20);

        return view('admin.form-submissions.index', compact('submissions'));
    }

    public function show(FormSubmission $formSubmission)
    {
        $formSubmission->load(['website', 'form']);

        return view('admin.form-submissions.show', compact('formSubmission'));
    }
}

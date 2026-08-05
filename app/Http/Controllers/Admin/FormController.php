<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $forms = Form::query()
            ->with('website')
            ->withCount('submissions')
            ->latest('created_at')
            ->paginate(15);

        return view('admin.forms.index', compact('forms'));
    }

    public function show(Form $form)
    {
        $form->load(['website', 'submissions' => fn ($query) => $query->latest('created_at')->limit(10)]);

        return view('admin.forms.show', compact('form'));
    }
}

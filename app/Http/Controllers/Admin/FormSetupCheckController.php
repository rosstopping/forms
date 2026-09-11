<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\FormSetupChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormSetupCheckController extends Controller
{
    public function __invoke(Request $request, Form $form, FormSetupChecker $checker): RedirectResponse
    {
        abort_unless($form->website?->isManageableBy($request->user()), 403);

        $form->forceFill([
            'setup_check_results' => $checker->check($form),
            'setup_checked_at' => now(),
        ])->save();

        return redirect()->route('admin.forms.show', $form)->with('status', 'Form setup checked. No submission, email or webhook was sent.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\AutoresponderHtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    public function __construct(private AutoresponderHtmlSanitizer $autoresponderHtmlSanitizer) {}

    public function index(Request $request)
    {
        $query = Form::query();

        if (! $request->user()?->isAdmin()) {
            $query->whereHas('website', fn ($query) => $query->accessibleTo($request->user()));
        }

        $forms = $query
            ->with('website')
            ->withCount('submissions')
            ->latest('created_at')
            ->paginate(15);

        return view('admin.forms.index', compact('forms'));
    }

    public function show(Form $form)
    {
        abort_unless($form->website?->isAccessibleBy(Auth::user()), 403);

        $form->load('website');

        return view('admin.forms.show', compact('form'));
    }

    public function update(Request $request, Form $form)
    {
        abort_unless($form->website?->isManageableBy(Auth::user()), 403);

        $data = $request->validate([
            'email_enabled_override' => ['nullable', 'boolean'],
            'email_subject_override' => ['nullable', 'string', 'max:255'],
            'autoresponder_mode' => ['required', 'string', 'in:inherit,enabled,disabled'],
            'autoresponder_subject_override' => ['nullable', 'string', 'max:255'],
            'autoresponder_body_override' => ['nullable', 'string', 'max:1000000'],
            'autoresponder_content_type_override' => ['nullable', 'string', 'in:text,html'],
            'autoresponder_delay_minutes_override' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'webhook_enabled_override' => ['nullable', 'boolean'],
            'webhook_url_override' => ['nullable', 'url', 'max:255'],
            'webhook_secret_override' => ['nullable', 'string', 'max:255'],
        ]);

        $emailRecipients = $this->parseEmailRecipients($request->input('email_recipients_override'));

        foreach ($emailRecipients as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email_recipients_override' => 'Please enter valid email addresses only.',
                ]);
            }
        }

        $data['email_enabled_override'] = (bool) $request->boolean('email_enabled_override');
        $data['autoresponder_enabled_override'] = match ($data['autoresponder_mode']) {
            'enabled' => true,
            'disabled' => false,
            default => null,
        };
        $resolvedContentType = $data['autoresponder_content_type_override'] ?? $form->website->autoresponder_content_type ?? 'text';

        if ($resolvedContentType === 'text') {
            $data['autoresponder_body_override'] = $this->autoresponderHtmlSanitizer->sanitize($data['autoresponder_body_override'] ?? null);
        }
        unset($data['autoresponder_mode']);
        $data['email_recipients_override'] = $emailRecipients;
        $data['webhook_enabled_override'] = (bool) $request->boolean('webhook_enabled_override');

        $form->fill($data)->save();

        return Redirect::route('admin.forms.show', $form)->with('status', 'Form settings updated.');
    }

    protected function parseEmailRecipients(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        } else {
            $items = [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item) => trim((string) $item), $items), static fn (string $item) => $item !== ''));
    }
}

<?php

namespace App\Http\Requests;

use App\Services\FormResolver;
use App\Services\SourceWebsiteResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $website = app(SourceWebsiteResolver::class)->resolve($this);
        $formName = $this->input('_form_name');

        if (is_string($formName) && app(FormResolver::class)->slug($formName) === 'popup'
            && $website?->domains()->where('domain', 'casaamoretenerife.com')->where('ownership_status', 'verified')->exists()) {
            return ['email' => ['bail', 'required', 'string', 'max:255', 'email:filter']];
        }

        if (! $website?->domains()->where('domain', 'northernprepsquad.co.uk')->where('ownership_status', 'verified')->exists()) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = 'Your enquiry has not been submitted. Please correct the following fields and try again.';

        throw new HttpResponseException($this->expectsJson()
            ? response()->json(['success' => false, 'message' => $message, 'errors' => $validator->errors()], 422)
            : response($message."\n\n".implode("\n", $validator->errors()->all()), 422)
                ->header('Content-Type', 'text/plain; charset=UTF-8'));
    }
}

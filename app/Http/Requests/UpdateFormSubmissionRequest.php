<?php

namespace App\Http\Requests;

use App\Models\FormSubmission;
use App\Models\LeadTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFormSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $submission = $this->route('form_submission');

        return $submission?->website?->isManageableBy($this->user()) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_tag'))) {
            $this->merge(['new_tag' => Str::squish($this->input('new_tag'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200', Rule::prohibitedIf(! $this->route('form_submission')?->is_manual)],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254', Rule::prohibitedIf(! $this->route('form_submission')?->is_manual)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50', Rule::prohibitedIf(! $this->route('form_submission')?->is_manual)],
            'message' => ['sometimes', 'nullable', 'string', 'max:10000', Rule::prohibitedIf(! $this->route('form_submission')?->is_manual)],
            'status' => ['required', 'string', Rule::in(FormSubmission::STATUSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'follow_up_at' => ['nullable', 'date'],
            'tags_present' => ['sometimes', 'boolean'],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['required', 'integer', 'distinct', Rule::exists(LeadTag::class, 'id')->where('website_id', $this->route('form_submission')?->website_id)],
            'new_tag' => ['nullable', 'string', 'max:40'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}

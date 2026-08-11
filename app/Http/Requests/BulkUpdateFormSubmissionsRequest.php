<?php

namespace App\Http\Requests;

use App\Models\FormSubmission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateFormSubmissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'selection_scope' => ['required', 'string', Rule::in(['page', 'all'])],
            'submission_ids' => ['nullable', 'required_if:selection_scope,page', 'array', 'min:1', 'max:100'],
            'submission_ids.*' => ['required', 'integer', 'distinct:strict', Rule::exists((new FormSubmission)->getTable(), 'id')],
            'action' => ['required', 'string', Rule::in(['update_status', 'mark_spam', 'delete'])],
            'status' => ['nullable', 'required_if:action,update_status', 'string', Rule::in(FormSubmission::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
            'filter_status' => ['nullable', 'string', Rule::in(FormSubmission::STATUSES)],
            'website_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'string', 'max:20'],
            'follow_up' => ['nullable', 'string', Rule::in(['overdue', 'today', 'upcoming', 'none'])],
            'spam' => ['nullable', 'string', Rule::in(['exclude', 'all', 'only'])],
        ];
    }
}

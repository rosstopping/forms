<?php

namespace App\Http\Requests;

use App\Models\Prospect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkProspectActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'research_again', 'delete', 'schedule_approved_email', 'send_approved_email'])],
            'selection_scope' => ['required', Rule::in(['page', 'all'])],
            'prospect_ids' => ['required_if:selection_scope,page', 'array', 'max:100'],
            'prospect_ids.*' => ['integer', 'distinct', 'exists:prospects,id'],
            'scheduled_send_at' => ['nullable', Rule::requiredIf($this->input('action') === 'schedule_approved_email'), 'date', 'after:now'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(Prospect::STATUSES)],
            'temperature' => ['nullable', 'string', Rule::in(Prospect::LEAD_TEMPERATURES)],
            'email_status' => ['nullable', 'string', Rule::in(['missing', 'present'])],
        ];
    }
}

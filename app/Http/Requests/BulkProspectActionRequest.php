<?php

namespace App\Http\Requests;

use App\Enums\ProspectLifecycleState;
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
            'action' => ['required', Rule::in([
                'approve', 'research_again', 'delete', 'schedule_approved_email', 'cancel_scheduled_email', 'mark_as_draft', 'send_approved_email',
                'pause', 'resume', 'force_warm', 'force_hot', 'clear_temperature_override', 'stop', 'mark_replied', 'mark_not_interested', 'mark_customer', 'mark_pilot',
            ])],
            'selection_scope' => ['required', Rule::in(['page', 'all'])],
            'prospect_ids' => ['required_if:selection_scope,page', 'array', 'max:100'],
            'prospect_ids.*' => ['integer', 'distinct', 'exists:prospects,id'],
            'scheduled_send_at' => ['nullable', Rule::requiredIf($this->input('action') === 'schedule_approved_email'), 'date', 'after:now'],
            'search' => ['nullable', 'string', 'max:15000'],
            'status' => ['nullable', 'string', Rule::in(Prospect::STATUSES)],
            'temperature' => ['nullable', 'string', Rule::in(Prospect::LEAD_TEMPERATURES)],
            'lifecycle_state' => ['nullable', Rule::enum(ProspectLifecycleState::class)],
            'email_status' => ['nullable', 'string', Rule::in(['missing', 'present'])],
        ];
    }
}

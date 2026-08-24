<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProspectLifecycleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->string('action')->toString();

        return [
            'action' => ['required', Rule::in([
                'pause', 'resume', 'stop', 'mark_replied', 'mark_not_interested',
                'mark_future_opportunity', 'mark_customer', 'mark_pilot',
                'force_warm', 'force_hot', 'clear_temperature_override', 'adjust_score', 'reset_score',
            ])],
            'future_opportunity_at' => ['nullable', Rule::requiredIf($action === 'mark_future_opportunity'), 'date', 'after:now'],
            'score_delta' => ['nullable', Rule::requiredIf($action === 'adjust_score'), 'integer', 'between:-100,100', 'not_in:0'],
            'reason' => ['nullable', Rule::requiredIf(in_array($action, ['adjust_score', 'reset_score'], true)), 'string', 'max:255'],
        ];
    }
}

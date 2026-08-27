<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteAutoresponderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website?->isManageableBy($this->user()) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'autoresponder_enabled' => ['required', 'boolean'],
            'autoresponder_subject' => ['nullable', 'string', 'max:255'],
            'autoresponder_body' => ['nullable', 'string', 'max:5000'],
            'autoresponder_content_type' => ['required', 'string', 'in:text,html'],
            'autoresponder_delay_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ];
    }
}

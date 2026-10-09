<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('website')) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
            ],
            'role' => ['prohibited'],
            'complimentary_membership_tier' => ['prohibited'],
            'complimentary_membership_ends_on' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }
}

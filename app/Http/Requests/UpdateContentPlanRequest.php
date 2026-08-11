<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPlanRequest extends FormRequest
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
        return [
            'enabled' => ['required', 'boolean'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'hour' => ['required', 'integer', 'between:0,23'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'audience' => ['nullable', 'string', 'max:20000'],
            'guidance' => ['nullable', 'string', 'max:20000'],
        ];
    }
}

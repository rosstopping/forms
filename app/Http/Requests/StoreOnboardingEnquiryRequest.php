<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingEnquiryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'agency' => ['nullable', 'string', 'max:150'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'goals' => ['required', 'string', 'max:3000'],
            '_sitewell_check' => ['nullable', 'string', 'max:0'],
        ];
    }
}

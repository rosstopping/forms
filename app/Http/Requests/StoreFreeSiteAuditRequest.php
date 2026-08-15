<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreeSiteAuditRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'website_url' => ['required', 'url:http,https', 'max:255'],
            'consent' => ['accepted'],
            '_sitewell_check' => ['nullable', 'prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $websiteUrl = trim((string) $this->input('website_url'));

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'website_url' => preg_match('/^https?:\/\//i', $websiteUrl) ? $websiteUrl : 'https://'.$websiteUrl,
        ]);
    }
}

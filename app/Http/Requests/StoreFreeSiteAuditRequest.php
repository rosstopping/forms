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
            'website_url' => ['required', 'url:http,https', 'max:255'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
            '_sitewell_check' => ['nullable', 'prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $websiteUrl = trim((string) $this->input('website_url'));

        $this->merge([
            'website_url' => preg_match('/^https?:\/\//i', $websiteUrl) ? $websiteUrl : 'https://'.$websiteUrl,
        ]);
    }

    public function attributes(): array
    {
        return ['website_url' => 'website address'];
    }
}

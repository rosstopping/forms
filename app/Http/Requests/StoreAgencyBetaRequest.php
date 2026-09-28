<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyBetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('marketing.agencies').'#join-beta';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'agency' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['required', 'url:http,https', 'max:2048'],
            'client_websites' => ['required', 'integer', 'min:0', 'max:100000'],
            'offers_seo' => ['required', 'in:yes,no'],
            'goals' => ['nullable', 'string', 'max:3000'],
            '_sitewell_check' => ['nullable', 'string', 'max:0'],
            '_honeypot' => ['nullable', 'string', 'max:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\CompetitorDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreWebsiteSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            try {
                $this->merge(['domain' => app(CompetitorDomain::class)->normalize($this->input('domain'))]);
            } catch (InvalidArgumentException) {
                $this->merge(['domain' => '']);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...UpdateWebsiteSetupRequest::businessRules(),
            'domain' => ['required', 'string', 'max:253', Rule::unique('website_domains', 'verified_domain')],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}

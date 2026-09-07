<?php

namespace App\Http\Requests;

use App\Models\WebsiteAudit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClaimWebsiteAuditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $audit = $this->route('websiteAudit');

        return $audit instanceof WebsiteAudit
            && ! $audit->hasExpired()
            && $audit->status === WebsiteAudit::STATUS_COMPLETED
            && $audit->claimed_at === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function attributes(): array
    {
        return ['email' => 'email address'];
    }
}

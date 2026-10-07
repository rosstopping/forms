<?php

namespace App\Http\Requests;

use App\Models\WebsiteAudit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteAuditGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $audit = $this->route('websiteAudit');

        return $audit instanceof WebsiteAudit
            && ! $audit->hasExpired()
            && $audit->isReadyToDisplay()
            && $audit->report_requested_at !== null
            && $this->session()->get('marketing.website_audit_review_ids.'.$audit->public_id) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_goal' => ['required', 'string', Rule::in(['enquiries', 'bookings', 'sales'])],
        ];
    }
}

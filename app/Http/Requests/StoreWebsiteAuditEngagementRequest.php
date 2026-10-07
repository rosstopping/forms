<?php

namespace App\Http\Requests;

use App\Models\WebsiteAudit;
use App\Models\WebsiteAuditVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteAuditEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $audit = $this->route('websiteAudit');

        return $audit instanceof WebsiteAudit && ! $audit->hasExpired() && $audit->isReadyToDisplay()
            && ! $this->user()?->isAdmin()
            && $this->session()->get('marketing.website_audit_id') === $audit->public_id;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'visit_id' => ['required', 'uuid'],
            'events' => ['required', 'array', 'min:1', 'max:10'],
            'events.*' => ['required', 'string', Rule::in(WebsiteAuditVisit::EVENTS)],
            'active_seconds' => ['required', 'integer', 'min:0', 'max:7200'],
            'scroll_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}

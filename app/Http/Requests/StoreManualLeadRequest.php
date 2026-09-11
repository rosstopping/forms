<?php

namespace App\Http\Requests;

use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $website = $this->attributes->get('currentWebsite');

        return $website instanceof Website && $website->isManageableBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'website_id' => ['required', 'integer', Rule::in([$this->attributes->get('currentWebsite')?->id])],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(FormSubmission::STATUSES)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}

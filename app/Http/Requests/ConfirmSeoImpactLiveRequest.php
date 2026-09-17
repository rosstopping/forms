<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSeoImpactLiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('seoImpact')->website_id === $this->route('website')->id, 404);

        return $this->route('website')->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return [
            'live_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Los_Angeles')->toDateString(), 'after_or_equal:'.$this->route('seoImpact')->created_at->setTimezone('America/Los_Angeles')->toDateString()],
            'actual_changes' => ['required', 'string', 'min:10', 'max:3000'],
            'deployment_evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmed_live' => ['accepted'],
        ];
    }
}

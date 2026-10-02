<?php

namespace App\Http\Requests;

use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoogleAdsTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website && $website->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return [
            'conversion_action_id' => ['required', 'regex:/^[1-9]\d*$/'],
            'lead_success_description' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}

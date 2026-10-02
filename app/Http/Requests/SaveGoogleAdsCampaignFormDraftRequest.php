<?php

namespace App\Http\Requests;

use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;

class SaveGoogleAdsCampaignFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website && $website->isManageableBy($this->user());
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'daily_budget' => ['nullable', 'string', 'max:20'],
            'max_cpc' => ['nullable', 'string', 'max:20'],
            'city_name' => ['nullable', 'string', 'max:80'],
            'radius_miles' => ['nullable', 'string', 'max:20'],
            'final_url' => ['nullable', 'string', 'max:2048'],
            'campaign_brief' => ['nullable', 'string', 'max:1000'],
            'keywords_text' => ['nullable', 'string', 'max:1000'],
            'headlines' => ['nullable', 'array', 'max:3'],
            'headlines.*' => ['nullable', 'string', 'max:30'],
            'descriptions' => ['nullable', 'array', 'max:2'],
            'descriptions.*' => ['nullable', 'string', 'max:90'],
        ];
    }
}

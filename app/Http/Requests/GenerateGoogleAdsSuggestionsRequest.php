<?php

namespace App\Http\Requests;

class GenerateGoogleAdsSuggestionsRequest extends StoreGoogleAdsCampaignDraftRequest
{
    public function rules(): array
    {
        $campaignRules = parent::rules();

        return [
            'final_url' => $campaignRules['final_url'],
            'city_name' => $campaignRules['city_name'],
            'campaign_brief' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

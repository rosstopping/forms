<?php

namespace App\Http\Requests;

use App\Services\WebsiteGrowthPlanPublisher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendWebsiteGrowthPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() && $this->route('website')->isManageableBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['recipient' => ['required', 'email', Rule::in(app(WebsiteGrowthPlanPublisher::class)->recipients($this->route('website')))]];
    }
}

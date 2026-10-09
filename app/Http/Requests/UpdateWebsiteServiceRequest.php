<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website
            && $this->user()?->isAdmin() === true
            && $website->isManageableBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_package' => ['required', Rule::in(array_keys(MembershipPlan::all()))],
            'service_status' => ['required', Rule::in(Website::SERVICE_STATUSES)],
            'service_ends_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}

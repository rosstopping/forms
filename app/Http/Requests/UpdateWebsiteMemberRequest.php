<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('website')) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required_without:complimentary_membership_tier', 'string', Rule::in(Website::MEMBER_ROLES)],
            'complimentary_membership_tier' => [Rule::prohibitedIf(! $this->user()?->isAdmin()), 'nullable', 'string', Rule::in([...array_keys(MembershipPlan::all()), 'existing'])],
            'complimentary_membership_ends_on' => $this->user()?->isAdmin()
                ? [Rule::excludeIf(! $this->filled('complimentary_membership_tier') || $this->input('complimentary_membership_tier') === 'existing'), 'nullable', 'date_format:Y-m-d']
                : ['prohibited'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Website;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteMemberRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists((new User)->getTable(), 'id'),
                Rule::notIn([$this->route('website')?->user_id]),
            ],
            'role' => ['required', 'string', Rule::in(Website::MEMBER_ROLES)],
        ];
    }
}

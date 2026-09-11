<?php

namespace App\Http\Requests;

use App\Models\LeadTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterFormSubmissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tag_id' => ['nullable', 'integer', Rule::exists(LeadTag::class, 'id')->where('website_id', $this->attributes->get('currentWebsite')?->id)],
        ];
    }
}

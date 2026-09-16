<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAiVisibilityPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('website')?->isManageableBy($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            'topic' => ['nullable', 'string', 'max:180'],
            'location' => ['nullable', 'string', 'max:180'],
            'priority' => ['required', Rule::in(['normal', 'high'])],
            'active' => ['required', 'boolean'],
            'seo_target_keyword_id' => ['nullable', 'integer', Rule::exists('seo_target_keywords', 'id')->where('website_id', $this->route('website')->id)],
        ];
    }
}

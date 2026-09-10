<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('form_submission')?->website?->isManageableBy($this->user()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['review_url' => ['nullable', 'string', 'url:https', 'max:2048']];
    }
}

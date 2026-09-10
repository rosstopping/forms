<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('form_submission')?->website?->isManageableBy($this->user()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['preview_hash' => ['required', 'string', 'size:64']];
    }
}

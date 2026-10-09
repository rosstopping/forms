<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeoWinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() && $this->route('seoWin')->website->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['save', 'approve', 'share', 'dismiss'])],
            'client_draft' => ['required_if:action,save,approve', 'nullable', 'string', 'max:2000']];
    }
}

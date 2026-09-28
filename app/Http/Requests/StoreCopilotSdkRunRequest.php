<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCopilotSdkRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true && $this->route('website')->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:240', 'regex:/\A(?:[a-zA-Z0-9_-]+\/){0,8}[a-zA-Z0-9_-]+\.html\z/'],
            'title' => ['required', 'string', 'max:200', 'not_regex:/[\x00-\x1f\x7f]/'],
        ];
    }
}

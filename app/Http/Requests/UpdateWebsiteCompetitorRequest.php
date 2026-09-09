<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('website')?->isManageableBy($this->user()) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['excluded' => ['required', 'boolean']];
    }
}

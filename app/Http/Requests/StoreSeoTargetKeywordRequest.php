<?php

namespace App\Http\Requests;

use App\Models\SeoTargetKeyword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSeoTargetKeywordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('website')?->isManageableBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:'.implode(',', SeoTargetKeyword::PRIORITIES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

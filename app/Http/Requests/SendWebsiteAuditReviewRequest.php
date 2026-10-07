<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendWebsiteAuditReviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priorities' => ['required', 'array', 'size:3'],
            'priorities.*' => ['required', 'array:title,impact,next_step'],
            'priorities.*.title' => ['required', 'string', 'max:150'],
            'priorities.*.impact' => ['required', 'string', 'max:1000'],
            'priorities.*.next_step' => ['required', 'string', 'max:1000'],
        ];
    }
}

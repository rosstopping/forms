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
            'loom_url' => ['required', 'string', 'max:2048', 'url:https', 'regex:~^https://(?:www\.)?loom\.com/share/[a-zA-Z0-9_-]+(?:\?[^\s]*)?$~'],
        ];
    }
}

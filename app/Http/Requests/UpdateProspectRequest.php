<?php

namespace App\Http\Requests;

use App\Models\Prospect;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProspectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $prospect = $this->route('prospect');

        return $prospect instanceof Prospect && $prospect->isAccessibleBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'status' => ['required', 'string', Rule::in(Prospect::STATUSES)],
            'outreach_subject' => ['nullable', 'string', 'max:255'],
            'outreach_body' => ['nullable', 'string', 'max:10000'],
            'showcase_video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'next_follow_up_at' => ['nullable', 'date'],
            'suppressed' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

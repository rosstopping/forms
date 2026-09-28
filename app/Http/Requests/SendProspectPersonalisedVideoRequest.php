<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendProspectPersonalisedVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video_url' => ['required', 'url:http,https', 'max:2048'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'action' => ['required', Rule::in(['save_draft', 'send_test', 'send_now', 'schedule'])],
            'scheduled_send_at' => ['exclude_unless:action,schedule', 'required', 'date', 'after:now'],
        ];
    }
}

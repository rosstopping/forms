<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWordPressConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32'],
            'site_url' => ['required', 'url:http,https', 'max:2048'],
            'plugin_version' => ['required', 'string', 'max:50'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('site_url') || app()->environment(['local', 'testing'])) {
                return;
            }

            if ($this->string('site_url')->startsWith('https://')) {
                return;
            }

            $validator->errors()->add('site_url', 'The WordPress URL must use HTTPS.');
        }];
    }
}

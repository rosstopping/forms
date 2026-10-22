<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPhpSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('url');
        if (is_string($url)) {
            $url = trim($url);
            $this->merge(['url' => str_contains($url, '://') ? $url : 'https://'.$url]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['url' => ['required', 'string', 'max:2048', 'url:http,https'], '_sitewell_check' => ['nullable', 'string', 'max:0']];
    }
}

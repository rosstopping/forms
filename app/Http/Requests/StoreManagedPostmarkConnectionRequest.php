<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreManagedPostmarkConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('website')?->isManageableBy($this->user()) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sending_domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $domain = Str::lower(trim($this->string('sending_domain')->toString()));
        $this->merge(['sending_domain' => Str::startsWith($domain, 'www.') ? Str::after($domain, 'www.') : $domain]);
    }
}

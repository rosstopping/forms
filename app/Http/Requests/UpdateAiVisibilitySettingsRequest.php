<?php

namespace App\Http\Requests;

use App\Services\AiVisibilityProviderRegistry;
use App\Services\AiVisibilitySetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAiVisibilitySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('website')?->isManageableBy($this->user());
    }

    protected function prepareForValidation(): void
    {
        if ($this->authorize()) {
            $defaults = app(AiVisibilitySetup::class)->settings($this->route('website'));
            foreach (['brand_name', 'frequency_days', 'aliases', 'services', 'locations'] as $field) {
                if (! $this->exists($field)) {
                    $this->merge([$field => $defaults->$field]);
                }
            }
        }
        foreach (['aliases', 'services', 'locations'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->input($field)))))]);
            }
        }
        $this->merge(['providers' => ['openai']]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'], 'brand_name' => ['required', 'string', 'min:2', 'max:150'],
            'frequency_days' => ['required', Rule::in([7, 14, 28])],
            'providers' => ['required', 'array', 'size:1'], 'providers.*' => [Rule::in(['openai'])],
            'aliases' => ['nullable', 'array', 'max:20'], 'aliases.*' => ['string', 'min:2', 'max:150'],
            'services' => ['nullable', 'array', 'max:15'], 'services.*' => ['string', 'max:180'],
            'locations' => ['nullable', 'array', 'max:15'], 'locations.*' => ['string', 'max:180'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('enabled')) {
                return;
            }
            $available = app(AiVisibilityProviderRegistry::class)->availability();
            if (! ($available['openai'] ?? false)) {
                $validator->errors()->add('enabled', 'AI checks are temporarily unavailable. Please contact support to enable tracking.');
            }
        }];
    }
}

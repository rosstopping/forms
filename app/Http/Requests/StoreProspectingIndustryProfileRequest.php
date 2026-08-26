<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProspectingIndustryProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'enabled' => $this->boolean('enabled'),
            'service_keywords' => $this->lines($this->input('service_keywords')),
            'search_keywords' => $this->lines($this->input('search_keywords')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('prospecting_industry_profiles', 'slug')],
            'enabled' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'estimated_customer_value' => ['required', 'integer', 'min:0', 'max:10000000'],
            'customer_value_band' => ['required', Rule::in(['very_high', 'high', 'medium', 'low'])],
            'service_keywords' => ['required', 'array', 'min:1', 'max:20'],
            'service_keywords.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'search_keywords' => ['required', 'array', 'min:1', 'max:10'],
            'search_keywords.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'minimum_position' => ['required', 'integer', 'min:1', 'max:100', 'lte:maximum_position'],
            'maximum_position' => ['required', 'integer', 'min:10', 'max:100', 'gte:minimum_position'],
            'maximum_site_size' => ['required', 'integer', 'min:1', 'max:100'],
            'automatic_import_score' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, string> */
    private function lines(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->filter('is_string')->map(fn (string $line): string => Str::squish($line))->filter()->unique(fn (string $line): string => Str::lower($line))->values()->all();
        }

        return collect(preg_split('/[\r\n,]+/', is_string($value) ? $value : '') ?: [])->map(fn (string $line): string => Str::squish($line))->filter()->unique(fn (string $line): string => Str::lower($line))->values()->all();
    }
}

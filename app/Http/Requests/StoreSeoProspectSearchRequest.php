<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSeoProspectSearchRequest extends FormRequest
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
        $services = $this->lines($this->input('service_keywords'));
        $keywords = $this->lines($this->input('keywords'));

        if ($keywords === [] && is_string($this->input('location'))) {
            $location = Str::lower(Str::squish($this->input('location')));
            $keywords = collect($services)->map(fn (string $service): string => Str::squish($service.' '.$location))->all();
        }

        $this->merge(['service_keywords' => $services, 'keywords' => $keywords]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'industry' => ['required', 'string', 'min:2', 'max:120'],
            'location' => ['required', 'string', 'min:2', 'max:120'],
            'service_keywords' => ['required', 'array', 'min:1', 'max:10'],
            'service_keywords.*' => ['required', 'string', 'min:2', 'max:120', 'distinct:ignore_case'],
            'keywords' => ['required', 'array', 'min:1', 'max:10'],
            'keywords.*' => ['required', 'string', 'min:2', 'max:160', 'distinct:ignore_case'],
            'minimum_position' => ['required', 'integer', 'min:1', 'max:100', 'lte:maximum_position'],
            'maximum_position' => ['required', 'integer', 'min:10', 'max:100', 'gte:minimum_position'],
            'maximum_pages' => ['required', 'integer', 'min:1', 'max:100'],
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

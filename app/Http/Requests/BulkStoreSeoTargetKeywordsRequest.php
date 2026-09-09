<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class BulkStoreSeoTargetKeywordsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->route('website')?->isManageableBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bulk_terms' => ['required', 'string', 'max:6000'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function terms(): array
    {
        return collect(preg_split('/\R/u', $this->string('bulk_terms')->toString()) ?: [])
            ->map(fn (string $term): string => Str::squish($term))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $terms = $this->terms();

            if ($terms === []) {
                $validator->errors()->add('bulk_terms', 'Enter at least one target keyword.');
            }

            if (count($terms) > 20) {
                $validator->errors()->add('bulk_terms', 'Enter no more than 20 target keywords.');
            }

            if (collect($terms)->contains(fn (string $term): bool => mb_strlen($term) > 255)) {
                $validator->errors()->add('bulk_terms', 'Each target keyword must be 255 characters or fewer.');
            }
        }];
    }
}

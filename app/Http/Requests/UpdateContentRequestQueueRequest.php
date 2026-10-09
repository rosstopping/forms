<?php

namespace App\Http\Requests;

use App\Services\SeoImpactTracker;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentRequestQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('website')?->isManageableBy($this->user()) === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('action') !== 'dependencies') {
            return;
        }
        foreach (['urls', 'files'] as $field) {
            $value = $this->input($field, []);
            if (is_string($value)) {
                $this->merge([$field => array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $value)))))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['hold', 'release', 'dependencies', 'classify', 'plan', 'enqueue'])],
            'coverage_reviewed' => ($this->input('action') === 'enqueue' && (bool) $this->route('contentRequest')?->discovery_context) ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'planned_for' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'work_type' => ['required_if:action,classify', 'sometimes', Rule::in(['new_article', 'new_page', 'optimisation', 'unspecified'])],
            'hold_reason' => ['required_if:action,hold', 'nullable', 'string', 'max:500'],
            'urls' => ['sometimes', 'array', 'max:5'],
            'urls.*' => ['required', 'string', 'max:700', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || app(SeoImpactTracker::class)->websiteUrls($this->route('website'), [$value]) === []) {
                    $fail('Required pages must be valid URLs on this website.');
                }
            }],
            'files' => [Rule::prohibitedIf(! $this->user()?->isAdmin()), 'sometimes', 'array', 'max:5'],
            'files.*' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! preg_match('~^[\w./@() -]+$~u', $value) || str_starts_with($value, '/')
                    || in_array('..', explode('/', $value), true) || in_array('.', explode('/', $value), true)) {
                    $fail('Use an exact repository-relative file path without traversal or wildcards.');
                }
            }],
        ];
    }
}

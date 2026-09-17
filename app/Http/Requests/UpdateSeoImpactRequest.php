<?php

namespace App\Http\Requests;

use App\Services\PixelUrlNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateSeoImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('seoImpact')->website_id === $this->route('website')->id, 404);

        return $this->route('website')->isManageableBy($this->user());
    }

    protected function prepareForValidation(): void
    {
        foreach (['target_urls', 'target_queries'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $this->input($field))))))]);
            }
        }
        if (is_string($this->input('country'))) {
            $this->merge(['country' => mb_strtolower($this->input('country'))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'], 'hypothesis' => ['required', 'string', 'max:2000'],
            'target_urls' => ['required', 'array', 'min:1', 'max:5'], 'target_urls.*' => ['required', 'url:http,https', 'max:700', 'distinct'],
            'target_queries' => ['present', 'array', 'max:10'], 'target_queries.*' => ['required', 'string', 'max:100', 'distinct'],
            'control_url' => ['nullable', 'url:http,https', 'max:700'], 'country' => ['nullable', 'alpha:ascii', 'size:3'],
            'device' => ['nullable', Rule::in(['DESKTOP', 'MOBILE', 'TABLET'])], 'primary_metric' => ['required', Rule::in(['clicks', 'ctr'])],
            'business_value' => ['required', 'integer', 'between:1,5'], 'confidence' => ['required', 'integer', 'between:1,5'], 'effort' => ['required', 'integer', 'between:1,5'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $normalizer = app(PixelUrlNormalizer::class);
            $domains = $this->route('website')->domains->map(fn ($domain): string => $normalizer->normalizeHost($domain->domain));
            $urls = $this->input('target_urls', []);
            foreach ([...$urls, ...($this->filled('control_url') ? [$this->input('control_url')] : [])] as $url) {
                try {
                    $matchesDomain = $domains->contains($normalizer->normalizeHost(parse_url($url, PHP_URL_HOST)));
                } catch (InvalidArgumentException) {
                    $matchesDomain = false;
                }
                if (! $matchesDomain || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_FRAGMENT)) {
                    $validator->errors()->add('target_urls', 'Use canonical URLs on this website, without credentials or fragments.');
                }
            }
            if ($validator->errors()->isEmpty() && $this->filled('control_url') && in_array($normalizer->normalizeForMatch($this->input('control_url')), array_map($normalizer->normalizeForMatch(...), $urls), true)) {
                $validator->errors()->add('control_url', 'Choose an unchanged comparison page outside the target pages.');
            }
        }];
    }
}

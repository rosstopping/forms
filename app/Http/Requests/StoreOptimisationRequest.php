<?php

namespace App\Http\Requests;

use App\Enums\OptimisationType;
use App\Models\Website;
use App\Services\OptimisationValueSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreOptimisationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website && $website->isManageableBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OptimisationType::class)],
            'selector' => [Rule::requiredIf(fn (): bool => in_array($this->string('type')->toString(), [
                'text', 'html', 'append_html', 'prepend_html', 'attribute', 'image_alt', 'internal_link',
            ], true)), 'nullable', 'string', 'max:1000', 'regex:/^[^\x00-\x1F\x7F]+$/u'],
            'target_description' => ['nullable', 'string', 'max:255'],
            'attribute' => [Rule::requiredIf($this->string('type')->toString() === 'attribute'), 'nullable', Rule::in(OptimisationValueSanitizer::ALLOWED_CHANGE_ATTRIBUTES)],
            'original_value' => ['nullable', 'string', 'max:100000'],
            'new_value' => ['required', 'string', 'max:100000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = OptimisationType::tryFrom($this->string('type')->toString());

            if (! $type || $validator->errors()->has('new_value')) {
                return;
            }

            try {
                $sanitized = app(OptimisationValueSanitizer::class)->sanitize(
                    $type,
                    $this->string('new_value')->toString(),
                    $this->string('attribute')->toString() ?: null,
                    $this->route('websiteHealthReportPage')?->url,
                );
                $this->merge(['new_value' => $sanitized]);
                $validator->setData([...$validator->getData(), 'new_value' => $sanitized]);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('new_value', $exception->getMessage());
            }
        }];
    }
}

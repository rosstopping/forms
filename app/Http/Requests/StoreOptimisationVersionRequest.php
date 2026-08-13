<?php

namespace App\Http\Requests;

use App\Models\Optimisation;
use App\Models\Website;
use App\Services\OptimisationValueSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreOptimisationVersionRequest extends FormRequest
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
            'new_value' => ['required', 'string', 'max:100000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $optimisation = $this->route('optimisation');

            if (! $optimisation instanceof Optimisation || $validator->errors()->has('new_value')) {
                return;
            }

            try {
                $sanitized = app(OptimisationValueSanitizer::class)->sanitize(
                    $optimisation->type,
                    $this->string('new_value')->toString(),
                    $optimisation->attribute,
                    $optimisation->url,
                );
                $this->merge(['new_value' => $sanitized]);
                $validator->setData([...$validator->getData(), 'new_value' => $sanitized]);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('new_value', $exception->getMessage());
            }
        }];
    }
}

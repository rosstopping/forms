<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Services\ContentSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPlanRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'additional_weekdays' => ['sometimes', 'array', 'max:'.max(0, app(ContentSchedule::class)->weeklyLimit($this->route('website')) - 1)],
            'additional_weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct', Rule::notIn([$this->input('weekday')])],
            'hour' => ['required', 'integer', 'between:0,23'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'audience' => ['nullable', 'string', 'max:20000'],
            'guidance' => ['nullable', 'string', 'max:20000'],
        ];
    }
}

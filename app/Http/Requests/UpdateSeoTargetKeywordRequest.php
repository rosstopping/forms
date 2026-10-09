<?php

namespace App\Http\Requests;

use App\Models\SeoTargetKeyword;
use App\Services\SeoImpactTracker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSeoTargetKeywordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');
        $keyword = $this->route('seoTargetKeyword');

        return $this->user() !== null && $website?->isManageableBy($this->user()) && $keyword?->website_id === $website?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:'.implode(',', SeoTargetKeyword::PRIORITIES)],
            'note' => ['nullable', 'string', 'max:1000'],
            'intended_url' => ['sometimes', 'nullable', 'string', 'max:700', function (string $attribute, mixed $value, \Closure $fail): void {
                if (app(SeoImpactTracker::class)->websiteUrls($this->route('website'), [$value]) === []) {
                    $fail('Choose a valid destination URL on this website.');
                }
            }],
            'assignment_role' => ['sometimes', 'required', 'in:primary,supporting'],
            'search_intent' => ['sometimes', 'nullable', 'in:informational,commercial,transactional,navigational'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Models\WebsiteAiQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportWebsiteAiQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');
        $question = $this->route('websiteAiQuestion');

        return $website instanceof Website
            && $question instanceof WebsiteAiQuestion
            && $question->website_id === $website->id
            && $question->user_id === $this->user()?->id
            && $website->isAccessibleBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

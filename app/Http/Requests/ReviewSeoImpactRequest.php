<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSeoImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('seoImpact')->website_id === $this->route('website')->id, 404);

        return $this->route('website')->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['keep', 'iterate', 'investigate', 'rollback', 'extend', 'cancel'])], 'decision_notes' => ['required', 'string', 'min:10', 'max:2000']];
    }
}

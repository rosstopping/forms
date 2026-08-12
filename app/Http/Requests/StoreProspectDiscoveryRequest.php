<?php

namespace App\Http\Requests;

use App\Services\OpenStreetMapProspectFinder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProspectDiscoveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area' => ['required', 'string', 'min:2', 'max:120'],
            'business_type' => ['required', 'string', 'in:'.implode(',', array_keys(OpenStreetMapProspectFinder::BUSINESS_TYPES))],
        ];
    }
}

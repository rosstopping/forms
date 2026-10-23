<?php

namespace App\Http\Requests;

use App\Models\Website;
use Illuminate\Foundation\Http\FormRequest;

class ReadAgentEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->isAdmin()) {
            return false;
        }
        $website = $this->route('website');
        if ($website instanceof Website) {
            abort_unless(Website::query()->accessibleTo($this->user())->whereKey($website->id)->exists(), 404);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'parent_id' => ['sometimes', 'integer', 'min:1'],
            'location_code' => ['sometimes', 'integer', 'min:1'],
            'language_code' => ['sometimes', 'string', 'max:12', 'regex:/^[a-zA-Z-]+$/'],
            'device' => ['sometimes', 'in:desktop,mobile,tablet'],
        ];
    }
}

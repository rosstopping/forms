<?php

namespace App\Http\Requests;

use App\Models\Website;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteRepositoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        [$installationId, $repositoryId] = array_pad(explode(':', $this->string('repository')->toString(), 2), 2, null);

        $this->merge([
            'github_installation_id' => $installationId,
            'repository_id' => $repositoryId,
            'project_path' => $this->filled('project_path') ? trim($this->string('project_path')->toString(), '/') : null,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website
            && ($this->user()?->isAdmin() === true || $website->user_id === $this->user()?->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string'],
            'github_installation_id' => ['required', 'integer', 'exists:github_installations,id'],
            'repository_id' => ['required', 'integer'],
            'project_path' => ['nullable', 'string', 'max:255', 'regex:/^(?!\/)(?!.*\.\.)(?!.*\/\/)[A-Za-z0-9._\/-]+$/'],
        ];
    }
}

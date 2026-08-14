<?php

namespace App\Http\Requests;

use App\Models\GithubInstallation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteBuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $pages = collect(preg_split('/[\r\n,]+/', $this->string('pages')->toString()) ?: [])
            ->map(fn (string $page): string => trim($page))
            ->filter()
            ->unique(fn (string $page): string => mb_strtolower($page))
            ->values()
            ->all();

        $this->merge([
            'pages' => $pages,
            'repository_name' => $this->string('repository_name')->trim()->lower()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'sector' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'pages' => ['required', 'array', 'min:1', 'max:12'],
            'pages.*' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'repository_name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
            'github_installation_id' => [
                'required',
                'integer',
                Rule::exists('github_installations', 'id')
                    ->where('status', GithubInstallation::STATUS_ACTIVE)
                    ->where('repository_selection', 'all'),
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}

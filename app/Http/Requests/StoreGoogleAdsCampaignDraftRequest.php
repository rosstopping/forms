<?php

namespace App\Http\Requests;

use App\Models\Website;
use App\Models\WebsiteDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreGoogleAdsCampaignDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website instanceof Website && $website->isManageableBy($this->user());
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'daily_budget' => ['required', 'numeric', 'min:1', 'max:1000'],
            'city_name' => ['required', 'string', 'max:80'],
            'radius_miles' => ['required', 'integer', 'between:1,50'],
            'final_url' => ['required', 'url:https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $domain = $this->route('website')?->primaryDomain();
                $host = parse_url((string) $value, PHP_URL_HOST);
                if (! $domain?->isVerified() || ! is_string($host) || WebsiteDomain::canonicalDomain(strtolower($host)) !== WebsiteDomain::canonicalDomain(strtolower($domain->domain))) {
                    $fail('The landing page must use this website’s verified domain.');
                }
            }],
            'keywords_text' => ['required', 'string', 'max:1000'],
            'headlines' => ['required', 'array', 'between:3,15'],
            'headlines.*' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'descriptions' => ['required', 'array', 'between:2,4'],
            'descriptions.*' => ['required', 'string', 'max:90', 'distinct:ignore_case'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'headlines' => $this->cleanTextLines($this->input('headlines', [])),
            'descriptions' => $this->cleanTextLines($this->input('descriptions', [])),
        ]);
    }

    /** @return list<mixed> */
    protected function cleanTextLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [$lines];
        }

        return array_values(array_filter(array_map(fn (mixed $line): mixed => is_string($line) ? trim($line) : $line, $lines), fn (mixed $line): bool => $line !== ''));
    }

    /** @return list<string> */
    public function keywords(): array
    {
        $keywords = collect(preg_split('/\R/', (string) $this->validated('keywords_text')))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->values();

        if ($keywords->count() < 1 || $keywords->count() > 10 || $keywords->contains(fn (string $keyword): bool => mb_strlen($keyword) > 80)) {
            throw ValidationException::withMessages(['keywords_text' => 'Enter 1 to 10 unique searches, each 80 characters or fewer.']);
        }

        return $keywords->all();
    }
}

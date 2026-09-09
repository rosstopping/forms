<?php

namespace App\Http\Requests;

use App\Services\CompetitorDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreWebsiteCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('website')?->isManageableBy($this->user()) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['domain' => ['required', 'string', 'max:253']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('domain'))) {
            try {
                $this->merge(['domain' => app(CompetitorDomain::class)->normalize($this->input('domain'))]);
            } catch (InvalidArgumentException) {
                $this->merge(['domain' => '']);
            }
        }
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $own = $this->route('website')->domains->pluck('domain')->map(fn (string $domain): string => app(CompetitorDomain::class)->normalize($domain));
            if ($own->contains($this->input('domain'))) {
                $validator->errors()->add('domain', 'Choose a competitor other than your own website.');
            }
        }];
    }
}

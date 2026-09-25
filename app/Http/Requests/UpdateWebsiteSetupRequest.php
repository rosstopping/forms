<?php

namespace App\Http\Requests;

use App\Models\WebsiteSetup;
use App\Services\CompetitorDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateWebsiteSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public static function businessRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'services' => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'string', 'max:1800'],
            'locations' => ['required', 'string', 'max:2000'],
            'difference' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        abort_unless(array_key_exists($this->route('step'), WebsiteSetup::STEPS), 404);

        return [...match ($this->route('step')) {
            'business' => self::businessRules(),
            'goals' => [
                'objective' => ['required', 'string', 'max:2000'],
                'priority_services' => ['required', 'string', 'max:2000'],
                'avoid' => ['nullable', 'string', 'max:2000'],
                'success_measure' => ['nullable', 'string', 'max:2000'],
            ],
            'connection' => ['method' => ['required', Rule::in(config('forms.pixel_ui_enabled') ? ['github', 'wordpress', 'pixel', 'later'] : ['github', 'wordpress', 'later'])]],
            'google' => ['local_business' => ['required', 'boolean']],
            'targets' => ['keywords' => ['nullable', 'string', 'max:6000'], 'competitors' => ['nullable', 'string', 'max:3000']],
            'content' => [
                'language' => ['required', 'string', 'max:100'],
                'tone' => ['required', 'string', 'max:500'],
                'facts' => ['nullable', 'string', 'max:2000'],
                'examples' => ['nullable', 'string', 'max:2000'],
                'avoid' => ['nullable', 'string', 'max:2000'],
                'guidance' => ['nullable', 'string', 'max:20000'],
            ],
            'delivery' => [
                'enabled' => ['required', 'boolean'],
                'weekday' => ['required', 'integer', 'between:0,6'],
                'additional_weekdays' => ['sometimes', 'array', 'max:2'],
                'additional_weekdays.*' => ['integer', 'between:0,6', 'distinct', Rule::notIn([$this->input('weekday')])],
                'hour' => ['required', 'integer', 'between:0,23'],
                'timezone' => ['required', Rule::in(timezone_identifiers_list())],
                'competitor_research_mode' => ['required', Rule::in(['manual', 'research', 'drafts'])],
                'health_reports_enabled' => ['required', 'boolean'],
                'weekly_ranking_reports_enabled' => ['required', 'boolean'],
                'email_enabled' => ['required', 'boolean'],
                'email_recipients' => ['nullable', 'string', 'max:3000', Rule::requiredIf($this->boolean('email_enabled'))],
            ],
            'review' => ['confirm' => ['accepted']],
        }, 'navigation' => ['sometimes', Rule::in(['next', 'save'])]];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->route('step') === 'targets') {
                $keywords = $this->lines('keywords');
                if (count($keywords) > 20 || collect($keywords)->contains(fn (string $term): bool => mb_strlen($term) > 255)) {
                    $validator->errors()->add('keywords', 'Enter up to 20 keywords, each no longer than 255 characters.');
                }
                $competitors = $this->lines('competitors');
                if (count($competitors) > 10) {
                    $validator->errors()->add('competitors', 'Enter up to 10 competitor domains.');
                }
                foreach ($competitors as $competitor) {
                    try {
                        $domain = app(CompetitorDomain::class)->normalize($competitor);
                        if ($this->route('website')->domains->contains(fn ($own): bool => app(CompetitorDomain::class)->normalize($own->domain) === $domain)) {
                            $validator->errors()->add('competitors', 'Choose competitors other than this website.');
                        }
                    } catch (InvalidArgumentException) {
                        $validator->errors()->add('competitors', 'Enter public competitor domains, one per line.');
                    }
                }
            }
            if ($this->route('step') === 'delivery') {
                $emails = preg_split('/[\s,;]+/', trim($this->input('email_recipients') ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                if (count($emails) > 10 || collect($emails)->contains(fn (string $email): bool => ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                    $validator->errors()->add('email_recipients', 'Enter up to 10 valid email addresses, separated by commas or new lines.');
                }
            }
        }];
    }

    /** @return list<string> */
    private function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->input($key) ?? '') ?: [])));
    }
}

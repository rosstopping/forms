<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFreeSiteAuditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'website_url' => ['bail', 'required', 'url:http,https', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $host = (string) parse_url($value, PHP_URL_HOST);
                if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $host)
                    || parse_url($value, PHP_URL_USER) !== null
                    || parse_url($value, PHP_URL_PASS) !== null
                    || parse_url($value, PHP_URL_PORT) !== null) {
                    $fail('Enter a complete website address, such as example.co.uk, without login details or a port.');

                    return;
                }
                if (! $this->domainResolvesToPublicAddress($host)) {
                    $fail('We could not find that website. Check the complete domain, including .com or .co.uk, and try again.');
                }
            }],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
            '_sitewell_check' => ['nullable', 'prohibited'],
        ];
    }

    protected function domainResolvesToPublicAddress(string $host): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));

        return $addresses !== [] && collect($addresses)->every(fn (string $address): bool => (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    }

    protected function prepareForValidation(): void
    {
        $websiteUrl = trim((string) $this->input('website_url'));

        $this->merge([
            'website_url' => preg_match('/^https?:\/\//i', $websiteUrl) ? $websiteUrl : 'https://'.$websiteUrl,
        ]);
    }

    public function attributes(): array
    {
        return ['website_url' => 'website address'];
    }
}

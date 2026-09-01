<?php

namespace App\Http\Requests;

use App\Models\WebsiteMailConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWebsiteAutoresponderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('mail_delivery_mode')) {
            $this->merge([
                'mail_delivery_mode' => $this->route('website')?->mailConnection?->mode ?? WebsiteMailConnection::MODE_LEGACY,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website?->isManageableBy($this->user()) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'autoresponder_enabled' => ['required', 'boolean'],
            'autoresponder_from_name' => ['nullable', 'string', 'max:255'],
            'autoresponder_from_email' => ['nullable', 'email', 'max:255'],
            'autoresponder_subject' => ['nullable', 'string', 'max:255'],
            'autoresponder_body' => ['nullable', 'string', 'max:1000000'],
            'autoresponder_content_type' => ['required', 'string', 'in:text,html'],
            'autoresponder_delay_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'mail_delivery_mode' => ['required', Rule::in([
                WebsiteMailConnection::MODE_LEGACY,
                WebsiteMailConnection::MODE_MANAGED,
                WebsiteMailConnection::MODE_CUSTOMER_POSTMARK,
            ])],
            'postmark_server_token' => [
                Rule::requiredIf(fn (): bool => $this->string('mail_delivery_mode')->toString() === WebsiteMailConnection::MODE_CUSTOMER_POSTMARK
                    && blank($this->route('website')?->mailConnection?->postmark_server_token)),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->string('mail_delivery_mode')->toString() !== WebsiteMailConnection::MODE_MANAGED) {
                    return;
                }

                $connection = $this->route('website')?->mailConnection;

                if ($connection?->postmark_server_id === null) {
                    $validator->errors()->add('mail_delivery_mode', 'Set up the managed Postmark domain before selecting this option.');
                }

                $fromEmail = $this->string('autoresponder_from_email')->toString();
                $fromDomain = str_contains($fromEmail, '@') ? str($fromEmail)->afterLast('@')->lower()->toString() : null;

                if (blank($fromEmail)) {
                    $validator->errors()->add('autoresponder_from_email', 'A From address is required for managed Postmark.');
                }

                if ($fromDomain && $fromDomain !== $connection?->sending_domain) {
                    $validator->errors()->add('autoresponder_from_email', 'The From address must use the verified managed sending domain.');
                }
            },
        ];
    }
}

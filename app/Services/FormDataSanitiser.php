<?php

namespace App\Services;

class FormDataSanitiser
{
	public function sanitise(array $payload): array
	{
		$sanitised = [];

		foreach ($payload as $key => $value) {
			if ($this->isInternalField($key)) {
				continue;
			}

			$sanitised[$key] = $this->normaliseValue($value);
		}

		return $sanitised;
	}

	public function isInternalField(string $key): bool
	{
		$internal = ['_token', '_form_name', '_form_success_url', '_form_error_url', '_honeypot', 'g-recaptcha-response', 'cf-turnstile-response'];

		return in_array($key, $internal, true);
	}

	protected function normaliseValue(mixed $value): mixed
	{
		if (is_array($value)) {
			return array_map(fn($item) => $this->normaliseScalar($item), $value);
		}

		return $this->normaliseScalar($value);
	}

	protected function normaliseScalar(mixed $value): string
	{
		if ($value === null) {
			return '';
		}

		$string = (string) $value;
		$string = str_replace("\0", '', $string);
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string) ?? $string;
		$string = str_replace(["\r\n", "\r"], "\n", $string);

		return e($string);
	}
}

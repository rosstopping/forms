<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Website;
use Illuminate\Support\Str;

class FormResolver
{
	public function resolve(Website $website, ?string $formName = null): ?Form
	{
		$normalizedName = $this->normalizeName($formName ?? 'Website form');

		return $website->forms()->where('slug', $this->slug($normalizedName))->first();
	}

	public function normalizeName(string $name): string
	{
		$normalized = preg_replace('/\s+/', ' ', trim($name));

		if (blank($normalized) || mb_strlen($normalized) > 120) {
			return 'Website form';
		}

		return $normalized;
	}

	public function slug(string $name): string
	{
		return Str::slug($name);
	}
}

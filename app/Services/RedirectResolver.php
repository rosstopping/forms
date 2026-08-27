<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Website;
use Illuminate\Http\Request;

class RedirectResolver
{
    public function resolveSubmittedSuccess(Request $request, Website $website): ?string
    {
        $submitted = $request->input('_form_success_url');

        return $this->isValidRedirect($submitted, $website) ? $submitted : null;
    }

    public function resolveSuccess(Request $request, Website $website, Form $form): ?string
    {
        if ($submitted = $this->resolveSubmittedSuccess($request, $website)) {
            return $submitted;
        }

        if (! blank($form->success_redirect_url_override)) {
            return $this->isValidRedirect($form->success_redirect_url_override, $website) ? $form->success_redirect_url_override : null;
        }

        if (! blank($website->success_redirect_url)) {
            return $this->isValidRedirect($website->success_redirect_url, $website) ? $website->success_redirect_url : null;
        }

        $referrer = $request->header('referer');

        if ($referrer && str_contains($referrer, 'form=success')) {
            return $this->appendQuery($referrer, ['form' => 'success', 'form_name' => $form->slug]);
        }

        return route('forms.submitted');
    }

    public function resolveError(Request $request, Website $website, Form $form): ?string
    {
        $submitted = $request->input('_form_error_url');

        if ($this->isValidRedirect($submitted, $website)) {
            return $submitted;
        }

        if (! blank($form->failure_redirect_url_override)) {
            return $this->isValidRedirect($form->failure_redirect_url_override, $website) ? $form->failure_redirect_url_override : null;
        }

        if (! blank($website->failure_redirect_url)) {
            return $this->isValidRedirect($website->failure_redirect_url, $website) ? $website->failure_redirect_url : null;
        }

        $referrer = $request->header('referer');

        if ($referrer && str_contains($referrer, 'form=error')) {
            return $this->appendQuery($referrer, ['form' => 'error', 'form_name' => $form->slug]);
        }

        return route('forms.submitted');
    }

    protected function isValidRedirect(?string $url, Website $website): bool
    {
        if (blank($url)) {
            return false;
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        if (! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);
        $host = preg_replace('/^www\./i', '', $host);

        foreach ($website->domains as $domain) {
            if (strtolower($domain->domain) === $host || strtolower($domain->domain) === 'www.'.$host) {
                return true;
            }
        }

        return false;
    }

    protected function appendQuery(string $url, array $params): string
    {
        $parts = parse_url($url);

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query = array_merge($query, $params);
        $parts['query'] = http_build_query($query);

        return $this->buildUrl($parts);
    }

    protected function buildUrl(array $parts): string
    {
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.'://'.$host.$port.$path.$query.$fragment;
    }
}

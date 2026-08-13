<?php

namespace App\Services;

use InvalidArgumentException;

class PixelUrlNormalizer
{
    public function normalizeForMatch(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('The Pixel URL must be an absolute HTTP or HTTPS URL.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('The Pixel URL must use HTTP or HTTPS.');
        }

        return $this->normalizeHost($parts['host']).$this->normalizePath($parts['path'] ?? '/');
    }

    public function hash(string $url): string
    {
        return hash('sha256', $this->normalizeForMatch($url));
    }

    public function normalizeHost(string $host): string
    {
        $host = rtrim(strtolower(trim($host)), '.');
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        if (function_exists('idn_to_utf8')) {
            $host = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }

        if ($host === '' || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidArgumentException('The Pixel URL contains an invalid hostname.');
        }

        return strtolower($host);
    }

    private function normalizePath(string $path): string
    {
        $path = $path === '' ? '/' : $path;
        $path = str_starts_with($path, '/') ? $path : '/'.$path;
        $path = preg_replace_callback(
            '/%[0-9a-f]{2}/i',
            fn (array $match): string => $this->normalizePercentEncoding($match[0]),
            $path,
        ) ?? $path;

        return $path === '/' ? $path : rtrim($path, '/');
    }

    private function normalizePercentEncoding(string $encoding): string
    {
        $character = rawurldecode($encoding);

        return preg_match('/^[A-Za-z0-9\-._~]$/', $character) === 1
            ? $character
            : strtoupper($encoding);
    }
}

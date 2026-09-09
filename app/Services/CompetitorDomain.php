<?php

namespace App\Services;

use InvalidArgumentException;

class CompetitorDomain
{
    public function normalize(string $value): string
    {
        $parts = parse_url(str_contains(trim($value), '://') ? trim($value) : 'https://'.trim($value));
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $host = preg_replace('/^www\./', '', $host);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || strlen($host) > 253) {
            throw new InvalidArgumentException('Enter a public competitor domain, such as example.com.');
        }

        return $host;
    }
}

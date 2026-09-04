<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

final class StaticPathResolver
{

    /**
     * @var array<string, string> 
     */
    private const CONTENT_TYPES = [
    'css'         => 'text/css; charset=UTF-8',
    'gif'         => 'image/gif',
    'html'        => 'text/html; charset=UTF-8',
    'ico'         => 'image/x-icon',
    'jpeg'        => 'image/jpeg',
    'jpg'         => 'image/jpeg',
    'js'          => 'text/javascript; charset=UTF-8',
    'json'        => 'application/json; charset=UTF-8',
    'map'         => 'application/json; charset=UTF-8',
    'png'         => 'image/png',
    'svg'         => 'image/svg+xml',
    'ttf'         => 'font/ttf',
    'txt'         => 'text/plain; charset=UTF-8',
    'webmanifest' => 'application/manifest+json; charset=UTF-8',
    'webp'        => 'image/webp',
    'woff'        => 'font/woff',
    'woff2'       => 'font/woff2',
    'xml'         => 'application/xml; charset=UTF-8',
    ];

    public function __construct( private readonly string $root )
    {
    }

    public function resolve( string $requestUri ): ?ResolvedStaticFile
    {
        $root = realpath($this->root);

        if ($root === false || ! is_dir($root) || ! is_readable($root) ) {
            return null;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This security boundary remains independently unit-testable without loading WordPress.
        $requestPath = parse_url($requestUri, PHP_URL_PATH);

        if (! is_string($requestPath) ) {
            return null;
        }

        $requestPath = $this->decode($requestPath);

        if (! $this->isSafeRequestPath($requestPath) ) {
            return null;
        }

        $relativePath = trim($requestPath, '/');

        foreach ( $this->candidatePaths($relativePath) as $candidate ) {
            $resolved = $this->resolveCandidate($root, $candidate);

            if ($resolved !== null ) {
                return $resolved;
            }
        }

        return null;
    }

    public function fallback404(): ?ResolvedStaticFile
    {
        $root = realpath($this->root);

        if ($root === false ) {
            return null;
        }

        return $this->resolveCandidate($root, '404.html');
    }

    private function decode( string $path ): string
    {
        for ( $iteration = 0; $iteration < 4; $iteration++ ) {
            $decoded = rawurldecode($path);

            if ($decoded === $path ) {
                return $decoded;
            }

            $path = $decoded;
        }

        return $path;
    }

    private function isSafeRequestPath( string $path ): bool
    {
        if (str_contains($path, "\0") || str_contains($path, '\\') ) {
            return false;
        }

        foreach ( explode('/', trim($path, '/')) as $segment ) {
            if ($segment === '' ) {
                continue;
            }

            if ($segment === '.' || $segment === '..' || str_starts_with($segment, '.') ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string> 
     */
    private function candidatePaths( string $relativePath ): array
    {
        if ($relativePath === '' ) {
            return [ 'index.html' ];
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($extension !== '' ) {
            return array_key_exists($extension, self::CONTENT_TYPES) ? [ $relativePath ] : [];
        }

        return [
        $relativePath . '/index.html',
        $relativePath . '.html',
        ];
    }

    private function resolveCandidate( string $root, string $relativePath ): ?ResolvedStaticFile
    {
        $candidate  = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($candidate === false
            || ! str_starts_with($candidate, $rootPrefix)
            || ! is_file($candidate)
            || ! is_readable($candidate) 
        ) {
            return null;
        }

        $extension   = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $contentType = self::CONTENT_TYPES[ $extension ] ?? null;

        if ($contentType === null ) {
            return null;
        }

        return new ResolvedStaticFile($candidate, $contentType, $extension === 'html');
    }
}

<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use DOMDocument;
use RuntimeException;

/** Validates compiled static output without WordPress or network access. */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Shared offline validator; callers escape errors when rendering them in Laravel or WordPress.
final class StaticArtifactValidator {

	/** @param list<string> $paths @param callable(string): string|false $read */
	public function validate( array $paths, callable $read ): void {
		$hasManifest = in_array( 'static-build-manifest.json', $paths, true );
		$manifest    = $hasManifest ? json_decode( (string) $read( 'static-build-manifest.json' ), true ) : null;
		if ( $hasManifest && ( ! is_array( $manifest ) || ( $manifest['version'] ?? null ) !== 1
			|| ! is_int( $manifest['pages'] ?? null ) || $manifest['pages'] < 1
			|| ! is_array( $manifest['assets'] ?? null ) ) ) {
			throw new RuntimeException( 'The supplied static-build-manifest.json is invalid. Expected version 1 with a positive page count and an assets map.' );
		}
		$available = array_fill_keys( $paths, true );
		$generated = [];
		foreach ( $paths as $path ) {
			if ( str_starts_with( $path, 'assets/fonts/' ) || str_starts_with( $path, 'assets/static/' ) ) {
				$fingerprinted = preg_match( '~^assets/(?:fonts|static)/[\w-]+\.([a-f0-9]{16})\.[a-z0-9]+$~D', $path, $match );
				if ( ! $hasManifest && ! $fingerprinted ) {
					continue;
				}
				if ( ! $fingerprinted
					|| substr( hash( 'sha256', (string) $read( $path ) ), 0, 16 ) !== $match[1] ) {
					throw new RuntimeException( "Invalid asset fingerprint: {$path}" );
				}
				$generated[ '/' . $path ] = true;
				if ( str_ends_with( $path, '.woff2' ) && substr( (string) $read( $path ), 0, 4 ) !== 'wOF2' ) {
					throw new RuntimeException( "Invalid WOFF2 asset: {$path}" );
				}
			}
		}
		foreach ( $manifest['assets'] ?? [] as $url ) {
			if ( ! is_string( $url ) || ! isset( $generated[ $url ] ) ) {
				throw new RuntimeException( 'The optimized artifact is missing a manifest asset.' );
			}
		}
		if ( $hasManifest && array_diff( array_keys( $generated ), array_values( $manifest['assets'] ) ) !== [] ) {
			throw new RuntimeException( 'Every generated asset must be listed in the manifest.' );
		}
		$visited = [];
		$check   = function ( string $url, string $from ) use ( &$check, &$visited, $available, $read ): void {
			$url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5 );
			if ( $url === '' || preg_match( '~^(?:data:|blob:|#|mailto:|tel:)~i', $url ) ) {
				return;
			}
			if ( preg_match( '~^(?:https://|//)~i', $url ) ) {
				return;
			}
			if ( preg_match( '~^(?:[a-z][a-z0-9+.-]*:|//)~i', $url ) ) {
				if ( preg_match( '~\.(?:css|woff2?|ttf|otf)(?:[?#]|$)~i', $url ) ) {
					throw new RuntimeException( "Remote stylesheet or font in {$from}: {$url}" );
				}

				return;
			}
			$path  = rawurldecode( explode( '?', explode( '#', $url )[0] )[0] );
			$parts = [];
			foreach ( explode( '/', str_starts_with( $path, '/' ) ? ltrim( $path, '/' ) : dirname( $from ) . '/' . $path ) as $part ) {
				if ( $part === '' || $part === '.' ) {
					continue;
				}
				if ( $part === '..' ) {
					if ( $parts === [] ) {
						throw new RuntimeException( 'Asset path escapes the artifact.' );
					}
					array_pop( $parts );
				} else {
					$parts[] = $part;
				}
			}
			$path = implode( '/', $parts );
			if ( ! isset( $available[ $path ] ) ) {
				throw new RuntimeException( "Missing static dependency in {$from}: {$url}" );
			}
			if ( str_ends_with( $path, '.css' ) && ! isset( $visited[ $path ] ) ) {
				$visited[ $path ] = true;
				$this->css( (string) $read( $path ), $path, $check );
			}
		};
		foreach ( $paths as $path ) {
			if ( ! preg_match( '/\.html?$/i', $path ) ) {
				continue;
			}
			$html = (string) $read( $path );
			if ( preg_match( '/\A(?:\xEF\xBB\xBF)?\s*---\R.*?\R---(?:\R|$)|\{%[-+]?\s*(?:include|extends|block|macro|import|from|set|if|for)\b/s', $html ) ) {
				throw new RuntimeException( "Unbuilt template found in {$path}" );
			}
			$document = new DOMDocument();
			$previous = libxml_use_internal_errors( true );
			try {
				$document->loadHTML( $html !== '' ? $html : '<html></html>', LIBXML_NONET );
			} finally {
				libxml_clear_errors();
				libxml_use_internal_errors( $previous );
			}
			foreach ( $document->getElementsByTagName( '*' ) as $node ) {
				foreach ( [ 'src', 'poster', 'data-src', 'data-poster' ] as $attribute ) {
					if ( $node->hasAttribute( $attribute ) && $node->tagName !== 'iframe' ) {
						$check( $node->getAttribute( $attribute ), $path );
					}
				}
				if ( $node->tagName === 'link' && preg_match( '/(?:stylesheet|icon|preload|modulepreload)/i', $node->getAttribute( 'rel' ) ) ) {
					$check( $node->getAttribute( 'href' ), $path );
				}
				if ( $node->hasAttribute( 'srcset' ) ) {
					preg_match_all( '/(?:^|,\s*)([^\s,]+)(?:\s+[^,]+)?/', $node->getAttribute( 'srcset' ), $matches );
					foreach ( $matches[1] as $url ) {
						$check( $url, $path );
					}
				}
				$this->css( $node->getAttribute( 'style' ), $path, $check );
				if ( $node->tagName === 'style' ) {
					$this->css( $node->textContent, $path, $check );
				}
			}
		}
	}

	/** @param callable(string, string): void $check */
	private function css( string $css, string $from, callable $check ): void {
		preg_match_all( '~url\(\s*[\'"]?([^\)\'"\s]+)[\'"]?\s*\)|@import\s+[\'"]([^\'"]+)[\'"]~i', $css, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$check( $match[2] ?? $match[1], $from );
		}
	}
}

<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

final class BypassPolicy {

	/** @var list<string> */
	private const INTERNAL_PATH_PREFIXES = [
		'/wp-admin',
		'/wp-json',
		'/wp-login.php',
		'/wp-cron.php',
		'/wp-comments-post.php',
		'/xmlrpc.php',
	];

	public function shouldBypass(
		string $requestUri,
		string $requestMethod = 'GET',
		bool $isAdmin = false,
		bool $isAjax = false,
		bool $isCron = false,
		bool $isRest = false,
		bool $isCli = false,
	): bool {
		if ( ! in_array( strtoupper( $requestMethod ), [ 'GET', 'HEAD' ], true ) ) {
			return true;
		}

		if ( $isAdmin || $isAjax || $isCron || $isRest || $isCli ) {
			return true;
		}

        // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This policy remains independently unit-testable without loading WordPress.
		$path = parse_url( $requestUri, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return true;
		}

		foreach ( self::INTERNAL_PATH_PREFIXES as $prefix ) {
			if ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) || str_starts_with( $path, $prefix . '?' ) ) {
				return true;
			}
		}

		return false;
	}
}

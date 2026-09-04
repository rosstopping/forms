<?php

declare(strict_types=1);

if ( ! defined( 'SITEWELL_STATIC_FRONTEND_VERSION' ) ) {
	define( 'SITEWELL_STATIC_FRONTEND_VERSION', '0.2.0' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		public function __construct( private readonly string $message ) {}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

function __( string $text ): string {
	return $text;
}

function esc_html__( string $text ): string {
	return $text;
}

function esc_html( string $text ): string {
	return $text;
}

function sanitize_text_field( string $text ): string {
	return trim( strip_tags( $text ) );
}

function home_url( string $path = '/' ): string {
	return 'https://example.com' . $path;
}

function wp_json_encode( mixed $value ): string|false {
	return json_encode( $value );
}

/** @param array<string, mixed> $arguments */
function wp_remote_request( string $url, array $arguments ): array|WP_Error {
	$GLOBALS['sitewell_test_request'] = compact( 'url', 'arguments' );

	return $GLOBALS['sitewell_test_response'];
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_response_code( array $response ): int {
	return (int) $response['status'];
}

/** @param array<string, mixed> $response */
function wp_remote_retrieve_body( array $response ): string {
	return (string) $response['body'];
}

require_once dirname( __DIR__ ) . '/src/Contracts/StaticRootProvider.php';
require_once dirname( __DIR__ ) . '/src/ResolvedStaticFile.php';
require_once dirname( __DIR__ ) . '/src/StaticPathResolver.php';
require_once dirname( __DIR__ ) . '/src/BypassPolicy.php';
require_once dirname( __DIR__ ) . '/src/SitewellClient.php';

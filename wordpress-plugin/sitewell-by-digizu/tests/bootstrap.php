<?php

declare(strict_types=1);

if ( ! defined( 'SITEWELL_STATIC_FRONTEND_VERSION' ) ) {
	define( 'SITEWELL_STATIC_FRONTEND_VERSION', '1.0.7' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		public function __construct( private readonly string $message, private readonly ?string $description = null ) {}

		public function get_error_message(): string {
			return $this->description ?? $this->message;
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

function esc_url_raw( string $url ): string {
	return $url;
}

function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['sitewell_test_options'][ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool $autoload = false ): bool {
	$GLOBALS['sitewell_test_options'][ $key ] = $value;

	return true;
}

function wp_generate_password( int $length = 12 ): string {
	return substr( str_repeat( 'abcdefgh', $length ), 0, $length );
}

function wp_mkdir_p( string $path ): bool {
	return is_dir( $path ) || mkdir( $path, 0777, true );
}

function home_url( string $path = '/' ): string {
	return 'https://example.com' . $path;
}

function wp_json_encode( mixed $value ): string|false {
	return json_encode( $value );
}

/**
 * @param  array<string, mixed>  $arguments
 */
function wp_remote_request( string $url, array $arguments ): array|WP_Error {
	$GLOBALS['sitewell_test_request'] = compact( 'url', 'arguments' );

	return isset( $GLOBALS['sitewell_test_http'] ) ? ( $GLOBALS['sitewell_test_http'] )( $url, $arguments ) : $GLOBALS['sitewell_test_response'];
}

/**
 * @param  array<string, mixed>  $response
 */
function wp_remote_retrieve_response_code( array $response ): int {
	return (int) $response['status'];
}

/**
 * @param  array<string, mixed>  $response
 */
function wp_remote_retrieve_body( array $response ): string {
	return (string) $response['body'];
}

require_once dirname( __DIR__ ) . '/src/Contracts/StaticRootProvider.php';
require_once dirname( __DIR__ ) . '/src/Admin/SettingsPage.php';
require_once dirname( __DIR__ ) . '/src/StaticArtifactValidator.php';
require_once dirname( __DIR__ ) . '/src/StaticPublisher.php';
require_once dirname( __DIR__ ) . '/src/DeliveryRules.php';
require_once dirname( __DIR__ ) . '/src/DirectDelivery.php';
require_once dirname( __DIR__ ) . '/src/UploadsDelivery.php';
require_once dirname( __DIR__ ) . '/src/ReleaseInstaller.php';
require_once dirname( __DIR__ ) . '/src/ActiveStaticRootProvider.php';
require_once dirname( __DIR__ ) . '/src/ResolvedStaticFile.php';
require_once dirname( __DIR__ ) . '/src/StaticPathResolver.php';
require_once dirname( __DIR__ ) . '/src/BypassPolicy.php';
require_once dirname( __DIR__ ) . '/src/SitewellClient.php';

function is_multisite(): bool {
	return false;
}
function site_url( string $path = '/' ): string {
	return home_url( $path );
}
function wp_parse_url( string $url, int $component = -1 ): mixed {
	return parse_url( $url, $component );
}
function is_wp_error( mixed $response ): bool {
	return $response instanceof WP_Error;
}
function wp_remote_retrieve_header( array $response, string $name ): string {
	return $response['headers'][ $name ] ?? '';
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( string $path ): void {
		if ( is_file( $path ) || is_link( $path ) ) {
			unlink( $path );
		}
	}
}

function wp_cache_delete( string $key, string $group = '' ): bool {
	return true;
}

require_once dirname( __DIR__ ) . '/src/PluginUpdater.php';
function get_site_transient( string $key ): mixed {
	return $GLOBALS['sitewell_test_transients'][ $key ] ?? false;
}
function set_site_transient( string $key, mixed $value, int $expiration = 0 ): bool {
	$GLOBALS['sitewell_test_transients'][ $key ] = $value;

	return true;
}
function delete_site_transient( string $key ): bool {
	unset( $GLOBALS['sitewell_test_transients'][ $key ] );

	return true;
}
function download_url( string $url, int $timeout = 300 ): mixed {
	$GLOBALS['sitewell_test_download_url'] = $url;

	return $GLOBALS['sitewell_test_download'];
}

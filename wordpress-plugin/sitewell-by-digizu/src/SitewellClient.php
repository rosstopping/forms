<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use WP_Error;

final class SitewellClient {

	public function __construct( private readonly string $apiUrl ) {}

	/**
	 * @return array{connection_id: string, credential: string, webhook_secret: string|null, website: array{name: string, domain: string|null}}
	 */
	public function connect( string $code ): array {
		$data = $this->request(
			'POST',
			'/wordpress/connections',
			[
				'code'           => $code,
				'site_url'       => home_url( '/' ),
				'plugin_version' => SITEWELL_STATIC_FRONTEND_VERSION,
			]
		);

		$connection_id  = $data['connection_id'] ?? null;
		$credential     = $data['credential'] ?? null;
		$website        = $data['website'] ?? null;
		$webhook_secret = $data['webhook_secret'] ?? null;

		if ( ! is_string( $connection_id )
			|| ! preg_match( '/^wpc_[a-z0-9]{28}$/', $connection_id )
			|| ! is_string( $credential )
			|| ! str_starts_with( $credential, 'swp_' )
			|| ! is_array( $website )
			|| ! is_string( $website['name'] ?? null )
		) {
			throw new RuntimeException( esc_html__( 'Sitewell returned an invalid connection response.', 'sitewell-static-frontend' ) );
		}

		return [
			'connection_id'  => $connection_id,
			'credential'     => $credential,
			'webhook_secret' => is_string( $webhook_secret ) && str_starts_with( $webhook_secret, 'swh_' ) ? $webhook_secret : null,
			'website'        => [
				'name'   => sanitize_text_field( $website['name'] ),
				'domain' => is_string( $website['domain'] ?? null ) ? sanitize_text_field( $website['domain'] ) : null,
			],
		];
	}

	/**
	 * @param  array{connection_id: string, credential: string}  $connection
	 * @return array{release_id: string, commit_sha: string, checksum: string, size: int, download_url: string}|null
	 */
	public function currentRelease( array $connection, ?string $activeReleaseId ): ?array {
		$path = '/wordpress/connections/' . rawurlencode( $connection['connection_id'] ) . '/releases/current';

		if ( is_string( $activeReleaseId ) && $activeReleaseId !== '' ) {
			$path .= '?active_release=' . rawurlencode( $activeReleaseId );
		}

		$data = $this->request( 'GET', $path, [], $connection['credential'] );

		if ( $data === [] ) {
			return null;
		}

		if ( ! is_string( $data['release_id'] ?? null )
			|| ! is_string( $data['commit_sha'] ?? null )
			|| ! is_string( $data['checksum'] ?? null )
			|| ! is_int( $data['size'] ?? null )
			|| ! is_string( $data['download_url'] ?? null )
			|| ! str_starts_with( $data['download_url'], rtrim( $this->apiUrl, '/' ) . '/' )
		) {
			throw new RuntimeException( esc_html__( 'Sitewell returned an invalid website update.', 'sitewell-static-frontend' ) );
		}

		return [
			'release_id'   => sanitize_text_field( $data['release_id'] ),
			'commit_sha'   => sanitize_text_field( $data['commit_sha'] ),
			'checksum'     => sanitize_text_field( $data['checksum'] ),
			'size'         => $data['size'],
			'download_url' => esc_url_raw( $data['download_url'] ),
		];
	}

	/**
	 * @param  array{connection_id: string, credential: string}  $connection
	 * @param  array{download_url: string}  $release
	 */
	public function downloadRelease( array $connection, array $release, string $filename ): void {
		$response = wp_remote_request(
			$release['download_url'],
			[
				'method'      => 'GET',
				'timeout'     => 60,
				'redirection' => 0,
				'sslverify'   => true,
				'stream'      => true,
				'filename'    => $filename,
				'headers'     => [
					'Accept'        => 'application/zip',
					'Authorization' => 'Bearer ' . $connection['credential'],
				],
			]
		);

		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			throw new RuntimeException( esc_html__( 'Sitewell could not download the website update.', 'sitewell-static-frontend' ) );
		}
	}

	/**
	 * @param  array{connection_id: string, credential: string}  $connection
	 */
	public function releaseActivated( array $connection, string $releaseId ): void {
		$this->request(
			'POST',
			'/wordpress/connections/' . rawurlencode( $connection['connection_id'] ) . '/releases/' . rawurlencode( $releaseId ) . '/activated',
			[],
			$connection['credential'],
		);
	}

	/**
	 * @param  array{connection_id: string, credential: string}  $connection
	 */
	public function heartbeat( array $connection ): void {
		$this->request(
			'POST',
			'/wordpress/connections/' . rawurlencode( $connection['connection_id'] ) . '/heartbeat',
			[
				'site_url'       => home_url( '/' ),
				'plugin_version' => SITEWELL_STATIC_FRONTEND_VERSION,
			],
			$connection['credential'],
		);
	}

	/**
	 * @param  array{connection_id: string, credential: string}  $connection
	 */
	public function disconnect( array $connection ): void {
		$this->request(
			'DELETE',
			'/wordpress/connections/' . rawurlencode( $connection['connection_id'] ),
			[],
			$connection['credential'],
			[ 401, 404 ],
		);
	}

	/**
	 * @param  array<string, string>  $body
	 * @param  int[]  $accepted_statuses
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, array $body = [], ?string $credential = null, array $accepted_statuses = [] ): array {
		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		];

		if ( $credential ) {
			$headers['Authorization'] = 'Bearer ' . $credential;
		}

		$response = wp_remote_request(
			rtrim( $this->apiUrl, '/' ) . $path,
			[
				'method'      => $method,
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => $headers,
				'body'        => $body === [] ? null : wp_json_encode( $body ),
			]
		);

		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$json   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( in_array( $status, $accepted_statuses, true ) ) {
			return [];
		}

		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $json ) && is_string( $json['message'] ?? null )
			? $json['message']
			: esc_html__( 'Sitewell could not complete the request.', 'sitewell-static-frontend' );

			if ( is_array( $json ) && is_string( $json['errors']['code'][0] ?? null ) ) {
				$message = $json['errors']['code'][0];
			}

			throw new RuntimeException( esc_html( sanitize_text_field( $message ) ) );
		}

		if ( $status === 204 ) {
			return [];
		}

		if ( ! is_array( $json['data'] ?? null ) ) {
			throw new RuntimeException( esc_html__( 'Sitewell returned an invalid response.', 'sitewell-static-frontend' ) );
		}

		return $json['data'];
	}
}

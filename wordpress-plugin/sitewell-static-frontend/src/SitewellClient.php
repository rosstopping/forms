<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use WP_Error;

final class SitewellClient {

	public function __construct( private readonly string $apiUrl ) {}

	/** @return array{connection_id: string, credential: string, website: array{name: string, domain: string|null}} */
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

		$connection_id = $data['connection_id'] ?? null;
		$credential    = $data['credential'] ?? null;
		$website       = $data['website'] ?? null;

		if ( ! is_string( $connection_id )
			|| ! preg_match( '/^wpc_[a-z0-9]{28}$/', $connection_id )
			|| ! is_string( $credential )
			|| ! str_starts_with( $credential, 'swp_' )
			|| ! is_array( $website )
			|| ! is_string( $website['name'] ?? null ) ) {
			throw new RuntimeException( esc_html__( 'Sitewell returned an invalid connection response.', 'sitewell-static-frontend' ) );
		}

		return [
			'connection_id' => $connection_id,
			'credential'    => $credential,
			'website'       => [
				'name'   => sanitize_text_field( $website['name'] ),
				'domain' => is_string( $website['domain'] ?? null ) ? sanitize_text_field( $website['domain'] ) : null,
			],
		];
	}

	/** @param array{connection_id: string, credential: string} $connection */
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

	/** @param array{connection_id: string, credential: string} $connection */
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

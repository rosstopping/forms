<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class DeploymentEndpoint {

	public function __construct( private readonly DeploymentManager $deployments ) {}

	public function register(): void {
		register_rest_route(
			'sitewell-static-frontend/v1',
			'/deploy',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'deploy' ],
				'permission_callback' => [ $this, 'authorize' ],
			]
		);
	}

	public function authorize( WP_REST_Request $request ): bool|WP_Error {
		$connection     = SettingsPage::connection();
		$authorization  = $request->get_header( 'authorization' );
		$providedSecret = str_starts_with( $authorization, 'Bearer ' ) ? substr( $authorization, 7 ) : '';
		$expectedSecret = is_array( $connection ) && is_string( $connection['webhook_secret'] ?? null )
			? $connection['webhook_secret']
			: '';

		if ( $expectedSecret === '' || ! hash_equals( $expectedSecret, $providedSecret ) ) {
			return new WP_Error( 'sitewell_forbidden', __( 'Invalid Sitewell deployment credentials.', 'sitewell-static-frontend' ), [ 'status' => 401 ] );
		}

		return true;
	}

	public function deploy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$releaseId = sanitize_text_field( (string) $request->get_param( 'release_id' ) );

		if ( ! preg_match( '/^wsr_[a-z0-9]{28}$/', $releaseId ) ) {
			return new WP_Error( 'sitewell_invalid_release', __( 'Sitewell supplied an invalid release.', 'sitewell-static-frontend' ), [ 'status' => 422 ] );
		}

		try {
			$installed = $this->deployments->checkForUpdate();
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'sitewell_deployment_failed', $exception->getMessage(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'installed' => $installed ], 200 );
	}
}

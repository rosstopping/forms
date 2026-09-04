<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Admin;

use RuntimeException;
use Sitewell\StaticFrontend\DeploymentManager;
use Sitewell\StaticFrontend\SitewellClient;

final class ConnectionActions {

	public function __construct(
		private readonly SitewellClient $client,
		private readonly DeploymentManager $deployments,
	) {}

	public function connect(): void {
		$this->authorize( 'sitewell_static_frontend_connect' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce and capability are verified immediately above.
		$code = sanitize_text_field( wp_unslash( $_POST['connection_code'] ?? '' ) );

		if ( $code === '' ) {
			$this->redirectWithNotice( 'error', __( 'Enter the connection code from Sitewell.', 'sitewell-static-frontend' ) );
		}

		try {
			$connection                    = $this->client->connect( $code );
			$connection['connected_at']    = gmdate( 'c' );
			$connection['last_checked_at'] = gmdate( 'c' );
			update_option( SettingsPage::OPTION_CONNECTION, $connection, false );
			$this->redirectWithNotice( 'success', __( 'WordPress is now connected to Sitewell.', 'sitewell-static-frontend' ) );
		} catch ( RuntimeException $exception ) {
			$this->redirectWithNotice( 'error', $exception->getMessage() );
		}
	}

	public function heartbeat(): void {
		$this->authorize( 'sitewell_static_frontend_heartbeat' );
		$connection = SettingsPage::connection();

		if ( $connection === null ) {
			$this->redirectWithNotice( 'error', __( 'Connect this plugin to Sitewell first.', 'sitewell-static-frontend' ) );
		}

		try {
			$this->client->heartbeat( $connection );
			$connection['last_checked_at'] = gmdate( 'c' );
			update_option( SettingsPage::OPTION_CONNECTION, $connection, false );
			$this->redirectWithNotice( 'success', __( 'The Sitewell connection is working.', 'sitewell-static-frontend' ) );
		} catch ( RuntimeException $exception ) {
			$this->redirectWithNotice( 'error', $exception->getMessage() );
		}
	}

	public function disconnect(): void {
		$this->authorize( 'sitewell_static_frontend_disconnect' );
		$connection = SettingsPage::connection();

		if ( $connection === null ) {
			$this->redirectWithNotice( 'success', __( 'The plugin is already disconnected.', 'sitewell-static-frontend' ) );
		}

		try {
			$this->client->disconnect( $connection );
			delete_option( SettingsPage::OPTION_CONNECTION );
			$this->redirectWithNotice( 'success', __( 'The Sitewell connection has been revoked.', 'sitewell-static-frontend' ) );
		} catch ( RuntimeException $exception ) {
			$this->redirectWithNotice( 'error', $exception->getMessage() );
		}
	}

	public function deploy(): void {
		$this->authorize( 'sitewell_static_frontend_deploy' );

		try {
			$installed = $this->deployments->checkForUpdate();
			$this->redirectWithNotice(
				'success',
				$installed
					? __( 'The latest Sitewell static release is now live.', 'sitewell-static-frontend' )
					: __( 'This website is already running the latest static release.', 'sitewell-static-frontend' ),
			);
		} catch ( RuntimeException $exception ) {
			$this->redirectWithNotice( 'error', $exception->getMessage() );
		}
	}

	private function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this connection.', 'sitewell-static-frontend' ) );
		}

		check_admin_referer( $action );
	}

	private function redirectWithNotice( string $type, string $message ): never {
		set_transient(
			'sitewell_static_frontend_notice_' . get_current_user_id(),
			[
				'type'    => $type,
				'message' => sanitize_text_field( $message ),
			],
			60,
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=sitewell-static-frontend' ) );
		exit;
	}
}

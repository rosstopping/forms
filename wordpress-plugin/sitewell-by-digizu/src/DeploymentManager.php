<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;

final class DeploymentManager {

	public function __construct(
		private readonly SitewellClient $client,
		private readonly ReleaseInstaller $installer,
	) {}

	public function checkForUpdate(): bool {
		$connection = SettingsPage::connection();

		if ( $connection === null ) {
			throw new RuntimeException( esc_html__( 'Connect this plugin to Sitewell first.', 'sitewell-static-frontend' ) );
		}

		$active  = SettingsPage::activeRelease();
		$release = $this->client->currentRelease( $connection, $active['release_id'] ?? null );

		if ( $release === null ) {
			if ( is_string( $active['release_id'] ?? null ) ) {
				$this->client->releaseActivated( $connection, $active['release_id'] );
			}

			$this->recordCheck( $connection );
			delete_option( 'sitewell_static_frontend_last_deployment_error' );

			return false;
		}

		$temporaryFile = wp_tempnam( $release['release_id'] . '.zip' );

		if ( ! is_string( $temporaryFile ) || $temporaryFile === '' ) {
			throw new RuntimeException( esc_html__( 'WordPress could not create a temporary update file.', 'sitewell-static-frontend' ) );
		}

		try {
			$this->client->downloadRelease( $connection, $release, $temporaryFile );
			$this->installer->install( $release, $temporaryFile );
			$this->client->releaseActivated( $connection, $release['release_id'] );
			$this->recordCheck( $connection );
			delete_option( 'sitewell_static_frontend_last_deployment_error' );
		} finally {
			if ( is_file( $temporaryFile ) ) {
				wp_delete_file( $temporaryFile );
			}
		}

		return true;
	}

	/**
	 * @param  array<string, mixed>  $connection
	 */
	private function recordCheck( array $connection ): void {
		$connection['last_checked_at'] = gmdate( 'c' );
		update_option( SettingsPage::OPTION_CONNECTION, $connection, false );
	}
}

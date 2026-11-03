<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;

final class DeploymentManager {

	public function __construct(
		private readonly SitewellClient $client,
		private readonly ReleaseInstaller $installer,
		private readonly string $lockDirectory,
	) {}

	public function checkForUpdate(): bool {
		if ( ! wp_mkdir_p( $this->lockDirectory ) ) {
			throw new RuntimeException( 'Could not create the deployment lock directory.' );
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- One updater across webhook, cron and manual requests, before any network work.
		$lock = fopen( $this->lockDirectory . '/.update.lock', 'c' );
		if ( $lock === false ) {
			throw new RuntimeException( 'Could not open the update lock.' );
		}
		try {
			$wouldBlock = 0;
			if ( ! flock( $lock, LOCK_EX | LOCK_NB, $wouldBlock ) ) {
				if ( $wouldBlock ) {
					throw new DeploymentInProgress( 'A Sitewell update is already in progress. Please check again shortly.' );
				}
				throw new RuntimeException( 'Could not lock the website update.' );
			}

			return $this->checkForUpdateLocked();
		} finally {
			flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing also releases the lock on exceptions or disconnects.
			fclose( $lock );
		}
	}

	private function checkForUpdateLocked(): bool {
		$connection = SettingsPage::connection();

		if ( $connection === null ) {
			throw new RuntimeException( esc_html__( 'Connect this plugin to Sitewell first.', 'sitewell-static-frontend' ) );
		}

		$this->client->heartbeat( $connection );
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

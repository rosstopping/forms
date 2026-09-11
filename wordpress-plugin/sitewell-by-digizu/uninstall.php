<?php

/**
 * Remove Sitewell credentials, settings, scheduled tasks, and downloaded releases.
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove one site's Sitewell data.
 */
function sitewell_static_frontend_uninstall_site(): void {
	$connection = get_option( 'sitewell_static_frontend_connection' );

	if ( is_array( $connection )
		&& is_string( $connection['connection_id'] ?? null )
		&& is_string( $connection['credential'] ?? null )
	) {
		$apiUrl = defined( 'SITEWELL_STATIC_FRONTEND_API_URL' )
			? (string) SITEWELL_STATIC_FRONTEND_API_URL
			: 'https://sitewell.digizu.co.uk/api';

		wp_remote_request(
			rtrim( $apiUrl, '/' ) . '/wordpress/connections/' . rawurlencode( $connection['connection_id'] ),
			[
				'method'      => 'DELETE',
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => [ 'Authorization' => 'Bearer ' . $connection['credential'] ],
			]
		);
	}

	delete_option( 'sitewell_static_frontend_enabled' );
	delete_option( 'sitewell_static_frontend_connection' );
	delete_option( 'sitewell_static_frontend_active_release' );
	delete_option( 'sitewell_static_frontend_previous_release' );
	delete_option( 'sitewell_static_frontend_last_deployment_error' );
	wp_clear_scheduled_hook( 'sitewell_static_frontend_check_updates' );

	$uploads      = wp_upload_dir();
	$releasesPath = defined( 'SITEWELL_STATIC_FRONTEND_RELEASES_PATH' )
		? (string) SITEWELL_STATIC_FRONTEND_RELEASES_PATH
		: rtrim( (string) ( $uploads['basedir'] ?? '' ), '/\\' ) . '/sitewell-static-frontend/releases';

	sitewell_static_frontend_remove_releases( $releasesPath );
}

/**
 * Recursively remove only the plugin-owned release directory.
 */
function sitewell_static_frontend_remove_releases( string $path ): void {
	$normalizedPath = wp_normalize_path( $path );

	if ( $normalizedPath === ''
		|| $normalizedPath === '/'
		|| basename( $normalizedPath ) !== 'releases'
		|| basename( dirname( $normalizedPath ) ) !== 'sitewell-static-frontend'
		|| ! is_dir( $path )
	) {
		return;
	}

	$items = scandir( $path );

	if ( $items === false ) {
		return;
	}

	foreach ( array_diff( $items, [ '.', '..' ] ) as $item ) {
		$itemPath = $path . DIRECTORY_SEPARATOR . $item;

		if ( is_link( $itemPath ) || is_file( $itemPath ) ) {
			wp_delete_file( $itemPath );
		} elseif ( is_dir( $itemPath ) ) {
			sitewell_static_frontend_remove_directory( $itemPath );
		}
	}

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only the validated plugin-owned release directory.
	rmdir( $path );
}

/**
 * Remove a child directory within the validated release root.
 */
function sitewell_static_frontend_remove_directory( string $path ): void {
	$items = scandir( $path );

	if ( $items === false ) {
		return;
	}

	foreach ( array_diff( $items, [ '.', '..' ] ) as $item ) {
		$itemPath = $path . DIRECTORY_SEPARATOR . $item;

		if ( is_link( $itemPath ) || is_file( $itemPath ) ) {
			wp_delete_file( $itemPath );
		} elseif ( is_dir( $itemPath ) ) {
			sitewell_static_frontend_remove_directory( $itemPath );
		}
	}

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only a child of the validated plugin-owned release directory.
	rmdir( $path );
}

if ( is_multisite() ) {
	$siteIds = get_sites( [ 'fields' => 'ids' ] );

	foreach ( $siteIds as $siteId ) {
		switch_to_blog( (int) $siteId );
		sitewell_static_frontend_uninstall_site();
		restore_current_blog();
	}
} else {
	sitewell_static_frontend_uninstall_site();
}

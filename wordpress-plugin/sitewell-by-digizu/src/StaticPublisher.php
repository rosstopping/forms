<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;

/** Publishes a validated release for a web server; never handles visitor requests. */
final class StaticPublisher {

	public function __construct( private readonly string $publicPath ) {}

	public function publish( string $releasePath ): void {
		if ( ! wp_mkdir_p( $this->publicPath ) ) {
			throw new RuntimeException( 'Could not create the static publishing directory.' );
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the validated local build manifest without any network dependency.
		$manifest = json_decode( (string) file_get_contents( $releasePath . '/static-build-manifest.json' ), true, 512, JSON_THROW_ON_ERROR );
		foreach ( array_unique( array_values( $manifest['assets'] ) ) as $url ) {
			$destination = $this->publicPath . $url;
			$source      = $releasePath . $url;
			if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
				throw new RuntimeException( 'Could not create the immutable asset directory.' );
			}
			if ( is_file( $destination ) ) {
				if ( hash_file( 'sha256', $source ) !== hash_file( 'sha256', $destination ) ) {
					throw new RuntimeException( 'An immutable asset cannot be overwritten.' );
				}

				continue;
			}
			$temporary = $destination . '.publishing-' . bin2hex( random_bytes( 8 ) );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Publish complete bytes using an atomic same-filesystem rename.
			if ( ! copy( $source, $temporary ) || ! rename( $temporary, $destination ) ) {
				if ( is_file( $temporary ) ) {
					wp_delete_file( $temporary );
				}
				throw new RuntimeException( 'Could not publish an immutable asset.' );
			}
		}
		$temporary = $this->publicPath . '/current-' . bin2hex( random_bytes( 8 ) );
		if ( ! symlink( $releasePath, $temporary ) ) {
			throw new RuntimeException( 'The static host requires filesystem symlink support.' );
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Switch the public symlink atomically.
		if ( ! rename( $temporary, $this->publicPath . '/current' ) ) {
			wp_delete_file( $temporary );
			throw new RuntimeException( 'Could not atomically switch the static publishing target.' );
		}
		clearstatcache( true );
	}
}

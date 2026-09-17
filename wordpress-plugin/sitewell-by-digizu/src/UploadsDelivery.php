<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;

/** Publishes ordinary media files; pages continue through the WordPress router. */
final class UploadsDelivery {

	public function __construct( private readonly string $directory, private readonly string $baseUrl ) {}

	public static function forWordPress(): self {
		$uploads = wp_upload_dir();

		return new self( rtrim( (string) $uploads['basedir'], '/' ) . '/sitewell-assets', rtrim( (string) $uploads['baseurl'], '/' ) . '/sitewell-assets' );
	}

	public function enable( ?DirectDelivery $direct ): void {
		if ( defined( 'SITEWELL_STATIC_FRONTEND_PUBLIC_PATH' ) ) {
			throw new RuntimeException( 'This site uses a custom static virtual host. Restore its WordPress routing before switching delivery modes.' );
		}
		$release = SettingsPage::activeRelease();
		if ( $release === null ) {
			throw new RuntimeException( 'Install a website update before enabling Sitewell.' );
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Serialize migration with release installation.
		$lock = fopen( dirname( $release['path'] ) . '/.install.lock', 'c' );
		if ( $lock === false ) {
			throw new RuntimeException( 'Could not open the deployment lock.' );
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				throw new RuntimeException( 'Could not lock deployment.' );
			}
			wp_cache_delete( SettingsPage::OPTION_ACTIVE_RELEASE, 'options' );
			$release = SettingsPage::activeRelease();
			if ( $release === null ) {
				throw new RuntimeException( 'The installed website update is unavailable.' );
			}
			$release['rendered_path'] = $this->prepare( $release['path'] );
			$direct?->disable();
			update_option( SettingsPage::OPTION_ACTIVE_RELEASE, $release, false );
			update_option( DirectDelivery::OPTION, false, false );
		} finally {
			flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the deployment lock.
			fclose( $lock );
		}
	}

	/** Prepare immutable public assets and private rewritten pages before activation. */
	public function prepare( string $releasePath ): string {
		$id = basename( $releasePath );
		if ( ! preg_match( '/^wsr_[a-z0-9]{28}$/D', $id ) ) {
			throw new RuntimeException( 'Invalid website release identifier.' );
		}
		$key    = $id . '-' . substr( hash( 'sha256', 'uploads-v1|' . $this->baseUrl ), 0, 12 );
		$public = $this->directory . '/' . $key;
		$pages  = $releasePath . '/.sitewell-pages-' . substr( $key, -12 );
		if ( is_file( $pages . '/index.html' ) && is_dir( $public ) ) {
			return $pages;
		}
		$files    = [];
		$assets   = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $releasePath, \FilesystemIterator::SKIP_DOTS ),
				static fn ( \SplFileInfo $file ): bool => ! str_starts_with( $file->getFilename(), '.' ) && ! $file->isLink(),
			)
		);
		foreach ( $iterator as $file ) {
			$path    = substr( $file->getPathname(), strlen( $releasePath ) + 1 );
			$files[] = $path;
			if ( ! in_array( strtolower( $file->getExtension() ), [ 'html', 'htm' ], true ) && ! in_array( $path, [ '_headers', 'static-build-manifest.json' ], true ) ) {
				$assets[ $path ] = $this->baseUrl . '/' . $key . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
			}
		}
		( new StaticArtifactValidator() )->validate( $files, fn ( string $path ): string|false => is_file( $releasePath . '/' . $path ) ? $this->read( $releasePath . '/' . $path ) : false );
		$staging     = $public . '-staging-' . bin2hex( random_bytes( 8 ) );
		$pageStaging = $pages . '-staging-' . bin2hex( random_bytes( 8 ) );
		try {
			foreach ( $files as $path ) {
				$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				$isPage    = in_array( $extension, [ 'html', 'htm' ], true );
				if ( ! $isPage && ! isset( $assets[ $path ] ) ) {
					continue;
				}
				$destination = ( $isPage ? $pageStaging : $staging ) . '/' . $path;
				if ( ! wp_mkdir_p( dirname( $destination ) ) ) {
					throw new RuntimeException( 'WordPress could not create the website media directory.' );
				}
				$contents = $this->read( $releasePath . '/' . $path );
				if ( in_array( $extension, [ 'html', 'htm', 'css', 'js', 'json', 'webmanifest', 'svg' ], true ) ) {
					$contents = $this->rewrite( $contents, $path, $assets );
					if ( $isPage ) {
						$contents = preg_replace_callback( '~<(?:script|link)\b[^>]*>~i', fn ( array $matches ): string => str_contains( $matches[0], $this->baseUrl . '/' . $key . '/' ) ? (string) preg_replace( '~\s+integrity\s*=\s*(["\']).*?\1~is', '', $matches[0] ) : $matches[0], $contents );
					}
				}
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Only validated static bytes in plugin-owned staging directories.
				if ( file_put_contents( $destination, $contents ) === false ) {
					throw new RuntimeException( 'WordPress could not publish the website media files.' );
				}
			}
			wp_mkdir_p( $staging );
			if ( ! is_dir( $public ) ) {
				$this->move( $staging, $public );
			}
			$this->move( $pageStaging, $pages );
		} finally {
			$this->remove( $staging );
			$this->remove( $pageStaging );
		}

		return $pages;
	}

	/** @param array<string, string> $assets */
	private function rewrite( string $contents, string $path, array $assets ): string {
		return (string) preg_replace_callback(
			'~(?<=["\'\s(=,])([^"\'\s()<>;,]+)~',
			function ( array $matches ) use ( $path, $assets ): string {
				$url    = html_entity_decode( str_replace( '\\/', '/', $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$origin = rtrim( home_url( '/' ), '/' );
				if ( str_starts_with( $url, $origin . '/' ) ) {
					$url = substr( $url, strlen( $origin ) );
				}
				if ( str_starts_with( $url, '#' ) || str_starts_with( $url, '//' ) || preg_match( '~^[a-z][a-z0-9+.-]*:~i', $url ) ) {
					return $matches[1];
				}
				$parts    = preg_split( '/(?=[?#])/', $url, 2 );
				$target   = rawurldecode( $parts[0] );
				$relative = str_starts_with( $target, '/' ) ? ltrim( $target, '/' ) : dirname( $path ) . '/' . $target;
				$segments = [];
				foreach ( explode( '/', $relative ) as $segment ) {
					if ( $segment === '..' ) {
						array_pop( $segments );
					} elseif ( $segment !== '.' && $segment !== '' ) {
						$segments[] = $segment;
					}
				}
				$normalized = implode( '/', $segments );

				if ( ! isset( $assets[ $normalized ] ) ) {
					return $matches[1];
				}
				$replacement = $assets[ $normalized ] . ( $parts[1] ?? '' );

				return str_contains( $matches[1], '\\/' ) ? str_replace( '/', '\\/', $replacement ) : $replacement;
			},
			$contents
		);
	}

	private function read( string $path ): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local artifact bytes.
		$contents = file_get_contents( $path );
		if ( $contents === false ) {
			throw new RuntimeException( 'Could not read the website files.' );
		}

		return $contents;
	}

	private function move( string $source, string $target ): void {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic publication of complete directories.
		if ( ! rename( $source, $target ) ) {
			throw new RuntimeException( 'Could not activate the prepared website files.' );
		}
	}

	private function remove( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $name ) {
			$child = $path . '/' . $name;
			if ( is_dir( $child ) && ! is_link( $child ) ) {
				$this->remove( $child );
			} else {
				wp_delete_file( $child );
			}
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove only our staging directories.
		rmdir( $path );
	}
}

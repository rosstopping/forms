<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;

/** All writes are confined to the publication directory and a marked .htaccess block. */
final class DirectDelivery {

	public const OPTION = 'sitewell_static_frontend_direct';

	public function __construct( private readonly string $root, private readonly string $server ) {}

	public static function forWordPress(): self {
		return new self( rtrim( ABSPATH, '/\\' ), strtolower( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) );
	}

	public function path(): string {
		return $this->root . '/sitewell-static';
	}

	public function isActive(): bool {
		clearstatcache( true, $this->path() . '/.enabled' );

		return is_file( $this->path() . '/.enabled' );
	}

	/** Serialize activation, deactivation, and deployment across PHP workers. */
	public function locked( callable $operation ): mixed {
		if ( ! wp_mkdir_p( $this->path() ) ) {
			throw new RuntimeException( 'The host must allow WordPress to create the sitewell-static directory in the website root.' );
		}

		return $this->lockAt( $this->path() . '/.delivery.lock', $operation );
	}

	private function lockAt( string $path, callable $operation ): mixed {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Cross-worker filesystem lock, not a visitor request.
		$lock = fopen( $path, 'c' );
		if ( $lock === false ) {
			throw new RuntimeException( 'Could not open the static delivery lock.' );
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				throw new RuntimeException( 'Could not lock static delivery.' );
			}

			return $operation();
		} finally {
			flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close our filesystem lock.
			fclose( $lock );
		}
	}

	public function enable(): void {
		$this->assertSupported();
		$active = SettingsPage::activeRelease();
		if ( $active === null ) {
			throw new RuntimeException( 'Install a Sitewell release before enabling fast delivery.' );
		}
		$this->lockAt( dirname( $active['path'] ) . '/.install.lock', fn () => $this->locked( fn () => $this->enableLocked() ) );
	}

	private function enableLocked(): void {
		wp_cache_delete( SettingsPage::OPTION_ACTIVE_RELEASE, 'options' );
		$release = SettingsPage::activeRelease();
		if ( $release === null ) {
			throw new RuntimeException( 'Install a Sitewell release before enabling fast delivery.' );
		}
		if ( $this->isActive() ) {
			return;
		}
		$token = $this->token();
		$rules = new DeliveryRules( $this->root, $token );
		$this->write( $this->path() . '/.htaccess', $rules->apacheStorage() );
		$this->publish( $release['path'] );
		$apache = stripos( $this->server, 'apache' ) !== false || stripos( $this->server, 'litespeed' ) !== false;
		$before = null;
		$after  = null;
		if ( $apache ) {
			$before = is_file( $this->root . '/.htaccess' ) ? $this->read( $this->root . '/.htaccess' ) : '';
			if ( substr_count( $before, '# BEGIN Sitewell Direct' ) !== substr_count( $before, '# END Sitewell Direct' ) || substr_count( $before, '# BEGIN Sitewell Direct' ) > 1 ) {
				throw new RuntimeException( 'The existing Sitewell .htaccess block needs host review.' );
			}
			$remaining = preg_replace( '/^# BEGIN Sitewell Direct\R.*?^# END Sitewell Direct\R?/ms', '', $before, -1, $replaced );
			if ( $replaced !== substr_count( $before, '# BEGIN Sitewell Direct' ) ) {
				throw new RuntimeException( 'The existing Sitewell .htaccess block needs host review.' );
			}
			$after = $rules->apache() . $remaining;
			$this->write( $this->root . '/.htaccess', $after );
		} else {
			$this->write( $this->path() . '/.nginx.conf', $rules->nginx() );
		}
		try {
			$this->verify( $release['path'], $token );
			$this->write( $this->path() . '/.enabled', '1' );
			$this->verify( $release['path'], null );
			update_option( self::OPTION, true, false );
		} catch ( RuntimeException $exception ) {
			wp_delete_file( $this->path() . '/.enabled' );
			if ( $this->isActive() ) {
				throw new RuntimeException( 'Verification failed and the delivery switch could not be removed. Ask the host to remove sitewell-static/.enabled immediately.' );
			}
			if ( $after !== null && $this->read( $this->root . '/.htaccess' ) === $after ) {
				$this->write( $this->root . '/.htaccess', (string) $before );
			}
			throw new RuntimeException(
				esc_html(
					$apache
					? $exception->getMessage() . ' The original routing has been restored. Ask the host to check rewrite, header and symlink support.'
					: $exception->getMessage() . ' Ask the host to install the Nginx configuration shown below, test and reload Nginx, then retry.'
				)
			);
		}
	}

	public function disable(): void {
		if ( ! is_dir( $this->path() ) ) {
			return;
		}
		$this->locked(
			function (): void {
				if ( $this->isActive() ) {
					wp_delete_file( $this->path() . '/.enabled' );
					if ( $this->isActive() ) {
						throw new RuntimeException( 'Could not restore WordPress routing. Ask the host to remove sitewell-static/.enabled.' );
					}
				}
			}
		);
	}

	/** Called under the delivery lock, after artifact validation and before updating the release option. */
	public function publish( string $releasePath ): void {
		$source = realpath( $releasePath );
		if ( $source === false || ! is_file( $source . '/index.html' ) ) {
			throw new RuntimeException( 'The installed release is unavailable.' );
		}
		$snapshot = $this->path() . '/versions/' . basename( $source );
		if ( ! preg_match( '/^wsr_[a-z0-9]{28}$/D', basename( $source ) ) ) {
			throw new RuntimeException( 'The installed release identifier is invalid.' );
		}
		if ( ! is_dir( $snapshot ) ) {
			$temporary = $snapshot . '-staging-' . bin2hex( random_bytes( 8 ) );
			if ( ! wp_mkdir_p( $temporary ) ) {
				throw new RuntimeException( 'Could not create the static publication.' );
			}
			try {
				$paths = [];
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
					$relative = substr( $file->getPathname(), strlen( $source ) + 1 );
					if ( $file->isLink() || ! $file->isFile() || preg_match( '~(^|/)\.|\.(php[0-9]?|phtml|phar)$~i', $relative ) ) {
						throw new RuntimeException( 'The installed release contains unsafe files.' );
					}
					if ( ! preg_match( '~^[a-zA-Z0-9_./-]+$~D', $relative ) ) {
						throw new RuntimeException( 'This release contains filenames that need host-specific URL routing.' );
					}
					$paths[] = $relative;
					if ( ! wp_mkdir_p( dirname( $temporary . '/' . $relative ) ) || ! copy( $file->getPathname(), $temporary . '/' . $relative ) ) {
						throw new RuntimeException( 'Could not copy the static publication.' );
					}
				}
				( new StaticArtifactValidator() )->validate( $paths, fn ( string $path ): string|false => is_file( $temporary . '/' . $path ) ? $this->read( $temporary . '/' . $path ) : false );
                // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Publish a complete immutable snapshot outside protected WordPress releases.
				if ( ! rename( $temporary, $snapshot ) ) {
					throw new RuntimeException( 'Could not publish the static snapshot.' );
				}
			} finally {
				if ( is_dir( $temporary ) ) {
					$this->removeSnapshot( $temporary );
				}
			}
		}
		$previous = is_link( $this->path() . '/current' ) ? readlink( $this->path() . '/current' ) : null;
		( new StaticPublisher( $this->path() ) )->publish( $snapshot );
		if ( $previous === $snapshot ) {
			return;
		}
		foreach ( glob( $this->path() . '/versions/wsr_*' ) as $old ) {
			if ( $old !== $snapshot && $old !== $previous ) {
				$this->removeSnapshot( $old );
			}
		}
	}

	private function removeSnapshot( string $path ): void {
		if ( ! str_starts_with( $path, $this->path() . '/versions/' ) ) {
			return;
		}
		if ( is_link( $path ) || ! is_dir( $path ) ) {
			wp_delete_file( $path );

			return;
		}
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $name ) {
			$this->removeSnapshot( $path . '/' . $name );
		}
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Only plugin-owned snapshots, never following symlinks.
		rmdir( $path );
	}

	public function configuration(): string {
		return is_file( $this->path() . '/.nginx.conf' ) ? $this->read( $this->path() . '/.nginx.conf' ) : '';
	}

	private function assertSupported(): void {
		if ( is_multisite() || rtrim( home_url( '/' ), '/' ) !== rtrim( site_url( '/' ), '/' ) || ! in_array( wp_parse_url( home_url( '/' ), PHP_URL_PATH ), [ null, '', '/' ], true ) ) {
			throw new RuntimeException( 'Fast delivery currently requires a single WordPress site installed at the domain root.' );
		}
		if ( defined( 'SITEWELL_STATIC_FRONTEND_PUBLIC_PATH' ) ) {
			throw new RuntimeException( 'This site uses the legacy custom static host. Ask the host to migrate its configuration before enabling managed delivery.' );
		}
		if ( ! preg_match( '/apache|litespeed|nginx/i', $this->server ) ) {
			throw new RuntimeException( 'This server needs a host-reviewed static delivery configuration. WordPress routing has not changed.' );
		}
	}

	private function token(): string {
		$path = $this->path() . '/.probe-token';
		if ( ! is_file( $path ) ) {
			$this->write( $path, bin2hex( random_bytes( 16 ) ) );
		}

		return $this->read( $path );
	}

	private function verify( string $releasePath, ?string $token ): void {
		$manifest = json_decode( $this->read( $releasePath . '/static-build-manifest.json' ), true, 512, JSON_THROW_ON_ERROR );
		$checks   = [ '/' => '/index.html' ];
		foreach ( array_slice( array_values( $manifest['assets'] ), 0, 1 ) as $asset ) {
			$checks[ $asset ] = $asset;
		}
		$checks[ '/sitewell-missing-' . bin2hex( random_bytes( 8 ) ) . '.css' ] = null;
		foreach ( $checks as $url => $file ) {
			$response = wp_remote_request(
				home_url( $url ) . ( $token === null ? '?sitewell_check=' . bin2hex( random_bytes( 8 ) ) : '?sitewell_probe=' . $token ),
				[
					'method'      => 'GET',
					'timeout'     => 10,
					'redirection' => 0,
					'sslverify'   => true,
					'headers'     => [ 'Cache-Control' => 'no-cache' ],
				]
			);
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== ( $file === null ? 404 : 200 )
				|| wp_remote_retrieve_header( $response, 'x-sitewell-delivery' ) !== 'static'
				|| ( $file !== null && ! hash_equals( (string) hash_file( 'sha256', $releasePath . $file ), hash( 'sha256', wp_remote_retrieve_body( $response ) ) ) ) ) {
				throw new RuntimeException( esc_html( 'Fast delivery could not be verified for ' . $url . '. Sitewell has not been enabled.' ) );
			}
		}
	}

	private function read( string $path ): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local managed configuration, never a remote URL.
		$contents = file_get_contents( $path );
		if ( $contents === false ) {
			throw new RuntimeException( 'Could not read static delivery configuration.' );
		}

		return $contents;
	}

	private function write( string $path, string $contents ): void {
		$temporary = $path . '-' . bin2hex( random_bytes( 8 ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.rename_rename -- Atomic configuration replacement on the same filesystem.
		if ( file_put_contents( $temporary, $contents, LOCK_EX ) === false || ! rename( $temporary, $path ) ) {
			wp_delete_file( $temporary );
			throw new RuntimeException( esc_html( 'Could not write static delivery configuration: ' . basename( $path ) ) );
		}
	}
}

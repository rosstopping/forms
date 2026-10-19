<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;

/** Owns only the public robots.txt, retaining its original bytes in WordPress. */
// phpcs:disable WordPress.WP.AlternativeFunctions -- Native local operations provide atomic replacement and checked deletion under a filesystem lock.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Preserve original file bytes in a private WordPress option.
final class RobotsPublisher {

	public const OPTION = 'sitewell_static_frontend_robots_backup';

	private const MAX_BYTES = 1048576;

	public function __construct( private readonly ?string $root, private readonly string $lockDirectory ) {}

	public static function forWordPress(): self {
		$uploads = wp_upload_dir();
		$root    = ! defined( 'SITEWELL_STATIC_FRONTEND_PUBLIC_PATH' ) && ! is_multisite() && rtrim( home_url( '/' ), '/' ) === rtrim( site_url( '/' ), '/' )
			&& in_array( wp_parse_url( home_url( '/' ), PHP_URL_PATH ), [ null, '', '/' ], true ) ? ABSPATH : null;

		return new self( $root, rtrim( (string) $uploads['basedir'], '/\\' ) . '/sitewell-static-frontend' );
	}

	public function synchronize( string $releasePath, bool $onlyWhenEnabled = false ): void {
		if ( $this->root === null ) {
			return;
		}
		$this->locked(
			function () use ( $releasePath, $onlyWhenEnabled ): void {
				if ( $onlyWhenEnabled ) {
					wp_cache_delete( Admin\SettingsPage::OPTION_ENABLED, 'options' );
					if ( ! Admin\SettingsPage::isEnabled() ) {
						return;
					}
				}
				$source = rtrim( $releasePath, '/\\' ) . '/robots.txt';
				if ( ! file_exists( $source ) && ! is_link( $source ) ) {
					$this->restoreLocked();

					return;
				}
				$contents = $this->read( $source );
				$target   = $this->target();
				$current  = $this->current();
				if ( ! is_writable( (string) $this->root ) ) {
					throw new RuntimeException( 'Could not publish robots.txt. Check WordPress file permissions.' );
				}
				$state = $this->state();
				if ( $state !== null ) {
					$this->assertOwned( $state, $current );
				} else {
					$state = [
						'path'     => $target,
						'original' => $current === null ? null : base64_encode( $current ),
						'mode'     => $current === null ? 0644 : fileperms( $target ) & 0777,
						'hashes'   => [],
					];
				}
				$hash            = hash( 'sha256', $contents );
				$state['hashes'] = array_values( array_unique( array_merge( $current === null ? [] : [ hash( 'sha256', $current ) ], [ $hash ] ) ) );
				// Save recovery information before replacing public bytes, including either side of a failed write.
				update_option( self::OPTION, $state, false );
				if ( get_option( self::OPTION ) !== $state ) {
					throw new RuntimeException( 'Could not save the original robots.txt. The public file was not changed.' );
				}
				if ( $current !== $contents ) {
					$this->write( $contents, $state['mode'] );
				}
			}
		);
	}

	public function restore( bool $disable = false ): void {
		if ( $this->root !== null ) {
			$this->locked(
				function () use ( $disable ): void {
					$this->restoreLocked();
					if ( $disable ) {
						update_option( Admin\SettingsPage::OPTION_ENABLED, false, false );
					}
				}
			);
		}
	}

	private function restoreLocked(): void {
		$state = $this->state();
		if ( $state === null ) {
			return;
		}
		$current  = $this->current();
		$original = $state['original'] === null ? null : base64_decode( $state['original'], true );
		if ( $original === false ) {
			throw new RuntimeException( 'The robots.txt backup is invalid. The public file was not changed.' );
		}
		if ( $current !== $original ) {
			$this->assertOwned( $state, $current );
			if ( $original === null ) {
				if ( ! unlink( $this->target() ) ) {
					throw new RuntimeException( 'Could not remove the Sitewell robots.txt. Check file permissions.' );
				}
			} else {
				$this->write( $original, $state['mode'] );
			}
		}
		delete_option( self::OPTION );
	}

	/** @return array{path: string, original: ?string, mode: int, hashes: list<string>}|null */
	private function state(): ?array {
		wp_cache_delete( self::OPTION, 'options' );
		$state = get_option( self::OPTION, null );
		if ( $state !== null && ( ! is_array( $state ) || ( $state['path'] ?? null ) !== $this->target()
			|| ! array_key_exists( 'original', $state ) || ( $state['original'] !== null && ! is_string( $state['original'] ) )
			|| ! is_int( $state['mode'] ?? null ) || ! is_array( $state['hashes'] ?? null ) ) ) {
			throw new RuntimeException( 'The robots.txt backup belongs to a different path or is invalid. Review it before continuing.' );
		}

		return $state;
	}

	/** @param array{hashes: list<string>} $state */
	private function assertOwned( array $state, ?string $current ): void {
		if ( $current === null || ! in_array( hash( 'sha256', $current ), $state['hashes'], true ) ) {
			throw new RuntimeException( 'robots.txt was changed outside Sitewell. The file and its backup have been preserved; review the conflict before continuing.' );
		}
	}

	private function target(): string {
		return rtrim( (string) $this->root, '/\\' ) . '/robots.txt';
	}

	private function current(): ?string {
		return ! file_exists( $this->target() ) && ! is_link( $this->target() ) ? null : $this->read( $this->target() );
	}

	private function read( string $path ): string {
		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_BYTES ) {
			throw new RuntimeException( 'robots.txt must be a readable regular file no larger than 1 MB. Symlinks are not supported.' );
		}
		$contents = file_get_contents( $path );
		if ( $contents === false || str_contains( $contents, "\0" ) ) {
			throw new RuntimeException( 'Could not read robots.txt as a text file.' );
		}

		return $contents;
	}

	private function write( string $contents, int $mode ): void {
		$temporary = $this->target() . '.sitewell-' . bin2hex( random_bytes( 8 ) );
		try {
			if ( file_put_contents( $temporary, $contents, LOCK_EX ) !== strlen( $contents )
				|| ! chmod( $temporary, $mode ) || ! rename( $temporary, $this->target() ) ) {
				throw new RuntimeException( 'Could not publish robots.txt. Check WordPress file permissions; the original backup is retained.' );
			}
		} finally {
			if ( is_file( $temporary ) ) {
				unlink( $temporary );
			}
		}
	}

	private function locked( callable $operation ): void {
		if ( ! wp_mkdir_p( $this->lockDirectory ) ) {
			throw new RuntimeException( 'Could not create the robots.txt lock directory.' );
		}
		$lock = fopen( $this->lockDirectory . '/.robots.lock', 'c' );
		if ( $lock === false ) {
			throw new RuntimeException( 'Could not open the robots.txt lock.' );
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				throw new RuntimeException( 'Could not lock robots.txt publication.' );
			}
			$operation();
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
}

<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend;

use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use ZipArchive;

final class ReleaseInstaller {

	private const MAX_FILES = 10000;

	private const MAX_ARCHIVE_BYTES = 50 * 1024 * 1024;

	private const MAX_BYTES = 150 * 1024 * 1024;

	private const ALLOWED_EXTENSIONS = [
		'avif',
		'css',
		'gif',
		'htm',
		'html',
		'ico',
		'jpeg',
		'jpg',
		'js',
		'json',
		'map',
		'mp3',
		'mp4',
		'ogg',
		'pdf',
		'png',
		'svg',
		'ttf',
		'txt',
		'webm',
		'webmanifest',
		'webp',
		'woff',
		'woff2',
		'xml',
	];

	public function __construct( private readonly string $releasesPath ) {}

	/**
	 * @param  array{release_id: string, checksum: string, size: int}  $release
	 */
	public function install( array $release, string $archivePath ): void {
		$this->validateRelease( $release, $archivePath );
		$this->protectReleaseDirectory();

		$releasePath   = rtrim( $this->releasesPath, '/\\' ) . DIRECTORY_SEPARATOR . $release['release_id'];
		$temporaryPath = $releasePath . '.installing-' . wp_generate_password( 8, false, false );

		if ( ! wp_mkdir_p( $temporaryPath ) ) {
			throw new RuntimeException( esc_html__( 'WordPress could not create the website update directory.', 'sitewell-static-frontend' ) );
		}

		try {
			$this->extract( $archivePath, $temporaryPath );

			if ( is_dir( $releasePath ) ) {
				$this->removeDirectory( $releasePath );
			}

            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- A same-filesystem rename provides the atomic release switch required here.
			if ( ! rename( $temporaryPath, $releasePath ) ) {
				throw new RuntimeException( esc_html__( 'WordPress could not activate the website update.', 'sitewell-static-frontend' ) );
			}
		} catch ( RuntimeException $exception ) {
			$this->removeDirectory( $temporaryPath );

			throw $exception;
		}

		$current  = get_option( SettingsPage::OPTION_ACTIVE_RELEASE );
		$previous = get_option( SettingsPage::OPTION_PREVIOUS_RELEASE );

		if ( is_array( $current ) && is_string( $current['path'] ?? null ) && $current['path'] !== $releasePath ) {
			update_option( SettingsPage::OPTION_PREVIOUS_RELEASE, $current, false );
		}

		update_option(
			SettingsPage::OPTION_ACTIVE_RELEASE,
			[
				'release_id'   => $release['release_id'],
				'path'         => $releasePath,
				'checksum'     => $release['checksum'],
				'activated_at' => gmdate( 'c' ),
			],
			false,
		);

		if ( is_array( $previous )
			&& is_string( $previous['path'] ?? null )
			&& $this->isManagedReleasePath( $previous['path'] )
			&& ( ! is_array( $current ) || ( $current['path'] ?? null ) !== $previous['path'] )
			&& $previous['path'] !== $releasePath
		) {
			$this->removeDirectory( $previous['path'] );
		}
	}

	private function isManagedReleasePath( string $path ): bool {
		$root = rtrim( $this->releasesPath, '/\\' ) . DIRECTORY_SEPARATOR;

		return str_starts_with( $path, $root )
		&& preg_match( '/^wsr_[a-z0-9]{28}$/', basename( $path ) ) === 1;
	}

	/**
	 * @param  array{release_id: string, checksum: string, size: int}  $release
	 */
	private function validateRelease( array $release, string $archivePath ): void {
		if ( ! preg_match( '/^wsr_[a-z0-9]{28}$/', $release['release_id'] )
			|| ! preg_match( '/^[a-f0-9]{64}$/', $release['checksum'] )
			|| ! is_file( $archivePath )
			|| $release['size'] > self::MAX_ARCHIVE_BYTES
			|| filesize( $archivePath ) !== $release['size']
			|| ! hash_equals( $release['checksum'], (string) hash_file( 'sha256', $archivePath ) )
		) {
			throw new RuntimeException( esc_html__( 'The downloaded website update failed verification.', 'sitewell-static-frontend' ) );
		}
	}

	private function extract( string $archivePath, string $destination ): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( esc_html__( 'The PHP ZIP extension is required to install website updates.', 'sitewell-static-frontend' ) );
		}

		$archive = new ZipArchive();

		if ( $archive->open( $archivePath ) !== true ) {
			throw new RuntimeException( esc_html__( 'The website update is not a valid ZIP archive.', 'sitewell-static-frontend' ) );
		}

		$totalBytes = 0;
		$hasIndex   = false;
		$paths      = [];

		try {
			if ( $archive->numFiles > self::MAX_FILES ) {
				throw new RuntimeException( esc_html__( 'The website update contains too many files.', 'sitewell-static-frontend' ) );
			}

			for ( $index = 0; $index < $archive->numFiles; $index++ ) {
				$stat = $archive->statIndex( $index );
				$name = is_array( $stat ) ? ( $stat['name'] ?? null ) : null;

				if ( ! is_string( $name ) ) {
					throw new RuntimeException( esc_html__( 'The website update contains an invalid file.', 'sitewell-static-frontend' ) );
				}

				$this->assertSafePath( $name );
				$this->assertRegularFileOrDirectory( $archive, $index );

				$normalizedPath = strtolower( rtrim( str_replace( '\\', '/', $name ), '/' ) );

				if ( isset( $paths[ $normalizedPath ] ) ) {
					throw new RuntimeException( esc_html__( 'The website update contains duplicate file paths.', 'sitewell-static-frontend' ) );
				}

				$paths[ $normalizedPath ] = true;
				$totalBytes              += (int) ( $stat['size'] ?? 0 );

				if ( $totalBytes > self::MAX_BYTES ) {
					throw new RuntimeException( esc_html__( 'The website update is too large.', 'sitewell-static-frontend' ) );
				}

				$hasIndex = $hasIndex || $name === 'index.html';
			}

			if ( ! $hasIndex || ! $archive->extractTo( $destination ) ) {
				throw new RuntimeException( esc_html__( 'The website update could not be extracted or has no index.html file.', 'sitewell-static-frontend' ) );
			}
		} finally {
			$archive->close();
		}
	}

	private function assertSafePath( string $path ): void {
		$normalized  = str_replace( '\\', '/', $path );
		$segments    = explode( '/', rtrim( $normalized, '/' ) );
		$extension   = strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
		$isDirectory = str_ends_with( $normalized, '/' );

		if ( $normalized === ''
			|| strlen( $normalized ) > 240
			|| str_starts_with( $normalized, '/' )
			|| preg_match( '/^[A-Za-z]:/', $normalized )
			|| in_array( '.', $segments, true )
			|| in_array( '..', $segments, true )
			|| array_filter( $segments, static fn ( string $segment ): bool => str_starts_with( $segment, '.' ) ) !== []
			|| str_contains( $normalized, "\0" )
			|| ( ! $isDirectory && ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) )
		) {
			throw new RuntimeException( esc_html__( 'The website update contains an unsafe file path.', 'sitewell-static-frontend' ) );
		}
	}

	private function assertRegularFileOrDirectory( ZipArchive $archive, int $index ): void {
		$operatingSystem = 0;
		$attributes      = 0;

		if ( ! $archive->getExternalAttributesIndex( $index, $operatingSystem, $attributes ) || $operatingSystem !== ZipArchive::OPSYS_UNIX ) {
			return;
		}

		$fileType = ( $attributes >> 16 ) & 0xF000;

		if ( $fileType !== 0 && ! in_array( $fileType, [ 0x4000, 0x8000 ], true ) ) {
			throw new RuntimeException( esc_html__( 'The website update contains an unsupported file type.', 'sitewell-static-frontend' ) );
		}
	}

	private function protectReleaseDirectory(): void {
		if ( ! wp_mkdir_p( $this->releasesPath ) ) {
			throw new RuntimeException( esc_html__( 'WordPress could not create the website update directory.', 'sitewell-static-frontend' ) );
		}

		$protectionFiles = [
			'.htaccess'  => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>',
			'index.php'  => "<?php\nexit;\n",
		];

		foreach ( $protectionFiles as $filename => $contents ) {
			$file = rtrim( $this->releasesPath, '/\\' ) . DIRECTORY_SEPARATOR . $filename;

			if ( is_file( $file ) ) {
				continue;
			}

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- These fixed server-protection files are written only within the plugin-owned release directory.
			if ( file_put_contents( $file, $contents, LOCK_EX ) === false ) {
				throw new RuntimeException( esc_html__( 'WordPress could not protect the website update directory.', 'sitewell-static-frontend' ) );
			}
		}
	}

	private function removeDirectory( string $path ): void {
		if ( ! is_dir( $path ) ) {
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
				$this->removeDirectory( $itemPath );
			}
		}

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes only a validated plugin-owned release directory after its contents are deleted.
		rmdir( $path );
	}
}

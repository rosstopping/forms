<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\DirectDelivery;
use Sitewell\StaticFrontend\ReleaseInstaller;
use Sitewell\StaticFrontend\UploadsDelivery;
use ZipArchive;

final class UploadsDeliveryTest extends TestCase {

	private string $root;

	private const URL = 'https://example.com/wp-content/uploads/sitewell-assets';

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/sitewell-uploads-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root );
		$GLOBALS['sitewell_test_options'] = [];
	}

	protected function tearDown(): void {
		$this->remove( $this->root );
	}

	public function test_it_publishes_assets_and_rewrites_dependencies_without_changing_source_or_page_links(): void {
		$font     = 'wOF2' . str_repeat( 'font', 10 );
		$fontPath = '/assets/fonts/site.' . substr( hash( 'sha256', $font ), 0, 16 ) . '.woff2';
		$css      = '@font-face{src:url(' . $fontPath . ')}body{background:url(../image.png)}';
		$cssPath  = '/assets/static/site.' . substr( hash( 'sha256', $css ), 0, 16 ) . '.css';
		$html     = '<link href="' . $cssPath . '" integrity="sha384-old"><img src="/assets/image.png" srcset="/assets/image.png 1x, /assets/image.png?size=2 2x"><a href="/about/">About</a><form action="/contact" method="post"></form><script src="https://external.example/app.js" integrity="sha384-external"></script>';
		$files    = [
			'index.html'            => $html,
			'nested/index.html'     => '<img src="../assets/image.png"><style>p{background:url(/assets/image.png)}</style>',
			'assets/image.png'      => 'image',
			ltrim( $fontPath, '/' ) => $font,
			ltrim( $cssPath, '/' )  => $css,
		];
		$manifest = [
			'/font'  => $fontPath,
			'/style' => $cssPath,
		];
		$this->install( $files, $manifest );
		$release  = get_option( SettingsPage::OPTION_ACTIVE_RELEASE );
		$pages    = $release['rendered_path'];
		$rendered = file_get_contents( $pages . '/index.html' );
		self::assertSame( $html, file_get_contents( $release['path'] . '/index.html' ) );
		self::assertStringContainsString( self::URL, $rendered );
		self::assertStringContainsString( '<a href="/about/">', $rendered );
		self::assertStringContainsString( 'action="/contact"', $rendered );
		self::assertStringNotContainsString( 'sha384-old', $rendered );
		self::assertStringContainsString( 'sha384-external', $rendered );
		self::assertStringNotContainsString( 'src="../assets', file_get_contents( $pages . '/nested/index.html' ) );
		$public = glob( $this->root . '/wp-content/uploads/sitewell-assets/wsr_*' )[0];
		self::assertStringContainsString( self::URL, file_get_contents( $public . $cssPath ) );
		self::assertStringNotContainsString( 'url(../image.png)', file_get_contents( $public . $cssPath ) );
		self::assertSame( $font, file_get_contents( $public . $fontPath ) );
		self::assertFileDoesNotExist( $public . '/static-build-manifest.json' );
		self::assertFileDoesNotExist( $public . '/_headers' );
		self::assertFileDoesNotExist( $public . '/index.html' );
		self::assertFileDoesNotExist( $this->root . '/.htaccess' );
		self::assertDirectoryDoesNotExist( $this->root . '/sitewell-static' );
		self::assertSame( $pages, $this->delivery()->prepare( $release['path'] ) );
	}

	public function test_it_installs_plain_compiled_assets_without_optimizer_metadata(): void {
		$this->install(
			[
				'index.html'              => '<link rel="stylesheet" href="https://cdn.example.com/site.css?v=1&amp;theme=light"><link rel="stylesheet" href="/assets/static/site.css"><h1>Plain site</h1>',
				'assets/static/site.css'  => '@font-face{src:url(../fonts/site.woff2)}',
				'assets/fonts/site.woff2' => 'wOF2font',
			],
			null
		);
		$release = get_option( SettingsPage::OPTION_ACTIVE_RELEASE );
		$public  = glob( $this->root . '/wp-content/uploads/sitewell-assets/wsr_*' )[0];
		self::assertFileDoesNotExist( $release['path'] . '/static-build-manifest.json' );
		self::assertFileDoesNotExist( $release['path'] . '/_headers' );
		self::assertStringContainsString( self::URL, file_get_contents( $release['rendered_path'] . '/index.html' ) );
		self::assertStringContainsString( self::URL, file_get_contents( $public . '/assets/static/site.css' ) );
		self::assertSame( 'wOF2font', file_get_contents( $public . '/assets/fonts/site.woff2' ) );
		self::assertStringContainsString( 'https://cdn.example.com/site.css?v=1&amp;theme=light', file_get_contents( $release['rendered_path'] . '/index.html' ) );
	}

	public function test_enabling_an_existing_release_needs_no_host_configuration(): void {
		$this->install(
			[
				'index.html' => '<img src="/image.png">',
				'image.png'  => 'bytes',
			],
			[],
			false
		);
		self::assertNull( get_option( SettingsPage::OPTION_ACTIVE_RELEASE )['rendered_path'] );
		$this->delivery()->enable( null );
		self::assertFileExists( get_option( SettingsPage::OPTION_ACTIVE_RELEASE )['rendered_path'] . '/index.html' );
		self::assertFalse( get_option( 'sitewell_static_frontend_direct' ) );
	}

	public function test_migration_removes_the_old_direct_switch_without_reconfiguring_the_server(): void {
		$this->install( [ 'index.html' => '<h1>Page</h1>' ], [], false );
		mkdir( $this->root . '/sitewell-static' );
		file_put_contents( $this->root . '/sitewell-static/.enabled', '1' );
		file_put_contents( $this->root . '/sitewell-static/.nginx.conf', 'existing configuration' );
		update_option( 'sitewell_static_frontend_direct', true );
		$this->delivery()->enable( new DirectDelivery( $this->root, 'nginx' ) );
		self::assertFileDoesNotExist( $this->root . '/sitewell-static/.enabled' );
		self::assertSame( 'existing configuration', file_get_contents( $this->root . '/sitewell-static/.nginx.conf' ) );
		self::assertFalse( get_option( 'sitewell_static_frontend_direct' ) );
	}

	public function test_old_upload_assets_survive_later_deployments_and_private_release_pruning(): void {
		$this->install(
			[
				'index.html' => '<img src="/image.png">',
				'image.png'  => 'first',
			],
			[]
		);
		$old = glob( $this->root . '/wp-content/uploads/sitewell-assets/wsr_*' )[0];
		$this->install(
			[
				'index.html' => '<img src="/image.png">',
				'image.png'  => 'second',
			],
			[],
			true,
			'wsr_1234567890abcdefghijklmnopqr'
		);
		$this->install(
			[
				'index.html' => '<img src="/image.png">',
				'image.png'  => 'third',
			],
			[],
			true,
			'wsr_abcdefghijklmnopqrstuvwxyz34'
		);
		self::assertSame( 'first', file_get_contents( $old . '/image.png' ) );
		self::assertDirectoryDoesNotExist( $this->root . '/releases/wsr_abcdefghijklmnopqrstuvwxyz12' );
		self::assertStringContainsString( 'wsr_abcdefghijklmnopqrstuvwxyz34', file_get_contents( get_option( SettingsPage::OPTION_ACTIVE_RELEASE )['rendered_path'] . '/index.html' ) );
	}

	public function test_actual_rowglo_upload_urls_are_physical_files_on_an_unmodified_nginx_root(): void {
		$fixture = getenv( 'ROWGLO_STATIC_FIXTURE' );
		if ( ! is_string( $fixture ) || ! is_dir( $fixture ) ) {
			self::markTestSkipped( 'Set ROWGLO_STATIC_FIXTURE to the completed Rowglo build.' );
		}
		$files = [];
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $fixture, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$files[ substr( $file->getPathname(), strlen( $fixture ) + 1 ) ] = file_get_contents( $file->getPathname() );
		}
		$this->install( $files, [] );
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		$port   = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
		fclose( $socket );
		$nginx = trim( shell_exec( 'command -v nginx' ) );
		self::assertNotSame( '', $nginx );
		$root = $this->root;
		file_put_contents( $root . '/nginx.conf', "pid $root/nginx.pid; error_log $root/error.log; events {} http { access_log off; client_body_temp_path $root/client; server { listen 127.0.0.1:$port; root $root; location / { try_files \$uri =404; } } }" );
		$process = proc_open(
			[ $nginx, '-c', $root . '/nginx.conf', '-g', 'daemon off;' ],
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'file', $root . '/out.log', 'a' ],
				2 => [ 'file', $root . '/err.log', 'a' ],
			],
			$pipes
		);
		fclose( $pipes[0] );
		try {
			for ( $i = 0; $i < 40; $i++ ) {
				$socket = @fsockopen( '127.0.0.1', $port );
				if ( $socket !== false ) {
					fclose( $socket );
					break;
				}
				usleep( 50000 );
			}
			$release = get_option( SettingsPage::OPTION_ACTIVE_RELEASE );
			$urls    = [];
			foreach ( [ $release['rendered_path'], $root . '/wp-content/uploads/sitewell-assets' ] as $directory ) {
				foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
					if ( ! in_array( $file->getExtension(), [ 'html', 'css', 'js' ], true ) ) {
						continue;
					}
					preg_match_all( '~https://example.com/wp-content/uploads/sitewell-assets/[^\s"\'()<>;,]+~', file_get_contents( $file->getPathname() ), $matches );
					$urls = array_merge( $urls, $matches[0] );
				}
			}
			$urls = array_unique( $urls );
			self::assertGreaterThan( 100, count( $urls ) );
			foreach ( $urls as $url ) {
				$path = parse_url( $url, PHP_URL_PATH );
				$curl = curl_init( 'http://127.0.0.1:' . $port . $path );
				curl_setopt_array(
					$curl,
					[
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_TIMEOUT        => 5,
					]
				);
				$body = curl_exec( $curl );
				self::assertSame( 200, curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ), $path );
				self::assertSame( hash_file( 'sha256', $root . rawurldecode( $path ) ), hash( 'sha256', $body ) );
			}
		} finally {
			proc_terminate( $process );
			proc_close( $process );
		}
	}

	private function delivery(): UploadsDelivery {
		return new UploadsDelivery( $this->root . '/wp-content/uploads/sitewell-assets', self::URL );
	}

	private function install( array $files, ?array $assets, bool $prepare = true, string $id = 'wsr_abcdefghijklmnopqrstuvwxyz12' ): void {
		$files  += $assets === null ? [] : [
			'_headers'                   => '/',
			'static-build-manifest.json' => json_encode(
				[
					'version' => 1,
					'pages'   => 2,
					'assets'  => $assets,
				]
			),
		];
		$archive = $this->root . '/release.zip';
		$zip     = new ZipArchive();
		$zip->open( $archive, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $files as $path => $content ) {
			$zip->addFromString( $path, $content );
		}
		$zip->close();
		( new ReleaseInstaller( $this->root . '/releases', null, null, $prepare ? $this->delivery() : null ) )->install(
			[
				'release_id' => $id,
				'checksum'   => hash_file( 'sha256', $archive ),
				'size'       => filesize( $archive ),
			],
			$archive
		);
	}

	private function remove( string $path ): void {
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $name ) {
			$child = $path . '/' . $name;
			if ( is_dir( $child ) && ! is_link( $child ) ) {
				$this->remove( $child );
			} else {
				unlink( $child );
			}
		}
		rmdir( $path );
	}
}

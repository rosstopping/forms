<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\DeliveryRules;
use Sitewell\StaticFrontend\DirectDelivery;
use Sitewell\StaticFrontend\ReleaseInstaller;
use ZipArchive;

final class DirectDeliveryTest extends TestCase {

	private string $directory;

	private string $release;

	private string $asset;

	private mixed $process = null;

	private int $port;

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/sitewell-direct-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory, 0755, true );
		$GLOBALS['sitewell_test_options'] = [];
		$this->release                    = $this->install( 'teal', 'wsr_abcdefghijklmnopqrstuvwxyz12' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['sitewell_test_http'] );
		if ( is_resource( $this->process ) ) {
			proc_terminate( $this->process );
			proc_close( $this->process );
		}
		$this->remove( $this->directory );
	}

	public function test_failed_preflight_restores_exact_apache_configuration(): void {
		$original = "# A host's rules\nRewriteEngine On\n# BEGIN WordPress\n# END WordPress\n";
		file_put_contents( $this->directory . '/.htaccess', $original );
		$GLOBALS['sitewell_test_http'] = static fn () => [
			'status' => 200,
			'body'   => 'wordpress',
		];
		$delivery                      = new DirectDelivery( $this->directory, 'Apache' );
		try {
			$delivery->enable();
			self::fail( 'A PHP response must not enable direct delivery.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'original routing has been restored', $exception->getMessage() );
		}
		self::assertSame( $original, file_get_contents( $this->directory . '/.htaccess' ) );
		self::assertFalse( $delivery->isActive() );
		self::assertFalse( get_option( DirectDelivery::OPTION, false ) );
	}

	public function test_nginx_requires_configuration_and_keeps_wordpress_enabled_until_verified(): void {
		$GLOBALS['sitewell_test_http'] = static fn () => [
			'status' => 200,
			'body'   => 'wordpress',
		];
		$delivery                      = new DirectDelivery( $this->directory, 'nginx' );
		try {
			$delivery->enable();
			self::fail( 'Unconfigured Nginx must not pass preflight.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'install the Nginx configuration', $exception->getMessage() );
		}
		self::assertStringContainsString( $this->directory . '/sitewell-static/.enabled', $delivery->configuration() );
		self::assertFileDoesNotExist( $this->directory . '/.htaccess' );
		self::assertFalse( $delivery->isActive() );
	}

	public function test_failed_public_check_removes_the_switch_and_restores_rules(): void {
		$original = "# Keep this host configuration exactly.\n";
		file_put_contents( $this->directory . '/.htaccess', $original );
		$GLOBALS['sitewell_test_http'] = function ( string $url ): array {
			if ( str_contains( $url, 'sitewell_check=' ) ) {
				return [
					'status' => 200,
					'body'   => 'WordPress',
				];
			}
			$path = parse_url( $url, PHP_URL_PATH );
			$file = $this->release . ( $path === '/' ? '/index.html' : $path );

			return [
				'status'  => is_file( $file ) ? 200 : 404,
				'body'    => is_file( $file ) ? file_get_contents( $file ) : '',
				'headers' => [ 'x-sitewell-delivery' => 'static' ],
			];
		};
		$delivery                      = new DirectDelivery( $this->directory, 'Apache' );
		try {
			$delivery->enable();
			self::fail( 'The enabled routing must pass an ordinary public request too.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'could not be verified', $exception->getMessage() );
		}
		self::assertFalse( $delivery->isActive() );
		self::assertSame( $original, file_get_contents( $this->directory . '/.htaccess' ) );
		self::assertFalse( get_option( DirectDelivery::OPTION, false ) );
	}

	public function test_unsupported_hosts_do_not_change_routing(): void {
		$delivery = new DirectDelivery( $this->directory, 'unknown' );
		try {
			$delivery->enable();
			self::fail( 'Unknown servers require explicit configuration.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'host-reviewed', $exception->getMessage() );
		}
		self::assertDirectoryDoesNotExist( $delivery->path() );
	}

	public function test_actual_nginx_routing_activation_deployment_and_rollback(): void {
		$this->exerciseServer( 'nginx' );
	}

	public function test_actual_apache_routing_activation_deployment_and_rollback(): void {
		$this->exerciseServer( 'Apache' );
	}

	public function test_actual_rowglo_build_on_both_servers(): void {
		$fixture = getenv( 'ROWGLO_STATIC_FIXTURE' );
		if ( ! is_string( $fixture ) || ! is_dir( $fixture ) ) {
			self::markTestSkipped( 'Set ROWGLO_STATIC_FIXTURE to the completed Rowglo artifact.' );
		}
		$zipPath = $this->directory . '/rowglo.zip';
		$zip     = new ZipArchive();
		$zip->open( $zipPath, ZipArchive::CREATE );
		$pages = [];
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $fixture, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$path = substr( $file->getPathname(), strlen( $fixture ) + 1 );
			$zip->addFile( $file->getPathname(), $path );
			if ( str_ends_with( $path, '.html' ) ) {
				$pages[] = $path;
			}
		}
		$zip->close();
		( new ReleaseInstaller( $this->directory . '/releases' ) )->install(
			[
				'release_id' => 'wsr_1234567890abcdefghijklmnopqr',
				'checksum'   => hash_file( 'sha256', $zipPath ),
				'size'       => filesize( $zipPath ),
			],
			$zipPath
		);
		$manifest = json_decode( file_get_contents( $fixture . '/static-build-manifest.json' ), true );
		self::assertGreaterThan( 100, count( $manifest['assets'] ) );
		foreach ( [ 'nginx', 'Apache' ] as $server ) {
			$delivery = new DirectDelivery( $this->directory, $server );
			wp_mkdir_p( $delivery->path() );
			$token = str_repeat( 'a', 32 );
			file_put_contents( $delivery->path() . '/.probe-token', $token );
			$this->start( $server, new DeliveryRules( $this->directory, $token ) );
			$GLOBALS['sitewell_test_http'] = fn ( string $url ) => $this->request( substr( $url, strlen( 'https://example.com' ) ) );
			$delivery->enable();
			foreach ( array_merge( array_values( $manifest['assets'] ), $pages ) as $path ) {
				$url      = '/' . ltrim( $path, '/' );
				$response = $this->request( $url );
				self::assertSame( 200, $response['status'], $server . ': ' . $url );
				self::assertSame( hash_file( 'sha256', $fixture . $url ), hash( 'sha256', $response['body'] ), $url );
				self::assertSame( 'static', $response['headers']['x-sitewell-delivery'] );
			}
			$delivery->disable();
			self::assertSame( 'WordPress', $this->request( '/' )['body'] );
			proc_terminate( $this->process );
			proc_close( $this->process );
			$this->process = null;
		}
	}

	private function exerciseServer( string $server ): void {
		$delivery = new DirectDelivery( $this->directory, $server );
		mkdir( $delivery->path(), 0755 );
		$token = str_repeat( 'a', 32 );
		file_put_contents( $delivery->path() . '/.probe-token', $token );
		$this->start( $server, new DeliveryRules( $this->directory, $token ) );
		self::assertSame( 'WordPress', $this->request( '/' )['body'] );
		$GLOBALS['sitewell_test_http'] = fn ( string $url ) => $this->request( substr( $url, strlen( 'https://example.com' ) ) );
		$delivery->enable();
		self::assertTrue( $delivery->isActive() );
		self::assertTrue( get_option( DirectDelivery::OPTION ) );
		self::assertSame( file_get_contents( $this->release . '/index.html' ), $this->request( '/' )['body'] );
		foreach ( [ '/service', '/service/', '/alias', '/alias/' ] as $url ) {
			self::assertSame( 'teal', $this->request( $url )['body'], $url );
		}
		foreach ( [ '/wp-admin/', '/wp-json/', '/wp-login.php', '/wp-cron.php', '/wp-comments-post.php', '/xmlrpc.php', '/?rest_route=%2F', '/?preview=true', '/wp-content/admin.css', '/wp-includes/admin.js', '/.well-known/acme-challenge/x' ] as $url ) {
			self::assertSame( 'WordPress', $this->request( $url )['body'], $url );
		}
		self::assertSame( 'WordPress', $this->request( '/contact', 'POST' )['body'] );
		foreach ( [ '/missing.css', '/missing.css/', '/unknown', '/_headers', '/static-build-manifest.json', '/test.php', '/.env' ] as $url ) {
			self::assertSame( 404, $this->request( $url )['status'], $url );
		}
		foreach ( [ '/sitewell-static/.probe-token', '/%73itewell-static/.probe-token' ] as $url ) {
			self::assertContains( $this->request( $url )['status'], [ 403, 404 ] );
		}
		self::assertSame( '', $this->request( '/', 'HEAD' )['body'] );
		$this->assertConcurrentAssets();
		$oldAsset = $this->asset;
		self::assertStringContainsString( 'immutable', $this->request( $oldAsset )['headers']['cache-control'] );
		self::assertStringNotContainsString( 'immutable', $this->request( '/' )['headers']['cache-control'] );
		$this->install( 'blue', 'wsr_1234567890abcdefghijklmnopqr', $delivery );
		self::assertStringContainsString( 'blue', $this->request( '/' )['body'] );
		self::assertSame( 200, $this->request( $oldAsset )['status'] );
		self::assertSame( 200, $this->request( $this->asset )['status'] );
		$delivery->disable();
		self::assertFalse( $delivery->isActive() );
		self::assertSame( 'WordPress', $this->request( '/' )['body'] );
		$delivery->enable();
		self::assertStringContainsString( 'blue', $this->request( '/' )['body'] );
	}

	private function assertConcurrentAssets(): void {
		$multi   = curl_multi_init();
		$handles = [];
		for ( $index = 0; $index < 12; $index++ ) {
			$handle = curl_init( 'http://127.0.0.1:' . $this->port . $this->asset . '?cold=' . $index );
			curl_setopt_array(
				$handle,
				[
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => 5,
				]
			);
			curl_multi_add_handle( $multi, $handle );
			$handles[] = $handle;
		}
		do {
			curl_multi_exec( $multi, $running );
			if ( $running > 0 ) {
				curl_multi_select( $multi, 0.1 );
			}
		} while ( $running > 0 );
		foreach ( $handles as $handle ) {
			self::assertSame( 200, curl_getinfo( $handle, CURLINFO_RESPONSE_CODE ) );
			self::assertSame( file_get_contents( $this->release . $this->asset ), curl_multi_getcontent( $handle ) );
			curl_multi_remove_handle( $multi, $handle );
		}
	}

	private function install( string $colour, string $id, ?DirectDelivery $delivery = null ): string {
		$css         = 'body{color:' . $colour . '}';
		$this->asset = '/assets/static/site.' . substr( hash( 'sha256', $css ), 0, 16 ) . '.css';
		$files       = [
			'index.html'                 => '<link rel="stylesheet" href="' . $this->asset . '"><h1>' . $colour . '</h1>',
			'service/index.html'         => $colour,
			'alias.html'                 => $colour,
			'missing.css/index.html'     => 'not an asset',
			ltrim( $this->asset, '/' )   => $css,
			'_headers'                   => '/',
			'static-build-manifest.json' => json_encode(
				[
					'version' => 1,
					'pages'   => 4,
					'assets'  => [ '/site.css' => $this->asset ],
				]
			),
		];
		$zipPath     = $this->directory . '/' . $id . '.zip';
		$zip         = new ZipArchive();
		$zip->open( $zipPath, ZipArchive::CREATE );
		foreach ( $files as $path => $contents ) {
			$zip->addFromString( $path, $contents );
		}
		$zip->close();
		( new ReleaseInstaller( $this->directory . '/releases', null, $delivery ) )->install(
			[
				'release_id' => $id,
				'checksum'   => hash_file( 'sha256', $zipPath ),
				'size'       => filesize( $zipPath ),
			],
			$zipPath
		);

		return get_option( SettingsPage::OPTION_ACTIVE_RELEASE )['path'];
	}

	private function start( string $server, DeliveryRules $rules ): void {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		self::assertNotFalse( $socket, 'Local server tests require permission to bind loopback sockets.' );
		$this->port = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
		fclose( $socket );
		$root = $this->directory;
		if ( $server === 'nginx' ) {
			$binary = trim( (string) shell_exec( 'command -v nginx' ) );
			if ( $binary === '' ) {
				self::markTestSkipped( 'Nginx is required for the server integration test.' );
			}
			$config  = "pid $root/server.pid;\nerror_log $root/error.log;\nworker_processes 1;\nevents { worker_connections 256; }\nhttp { access_log off; client_body_temp_path $root/client; types { text/html html; text/css css; } server { listen 127.0.0.1:$this->port;\n" . $rules->nginx() . "\nlocation / { return 200 'WordPress'; }\nlocation ~ \\.php$ { return 200 'WordPress'; }\nlocation ~ \\.(css|js)$ { return 200 'WordPress'; }\n} }";
			$command = [ $binary, '-c', $root . '/server.conf', '-g', 'daemon off;' ];
		} else {
			$binary = '/usr/sbin/httpd';
			if ( ! is_executable( $binary ) || ! is_dir( '/usr/libexec/apache2' ) ) {
				self::markTestSkipped( 'macOS Apache is required for this fixture.' );
			}
			$config = "ServerRoot $root\nPidFile $root/server.pid\nListen 127.0.0.1:$this->port\nServerName localhost\nKeepAlive Off\nErrorLog $root/error.log\n";
			foreach ( [ 'mpm_prefork', 'authz_core', 'authz_host', 'unixd', 'rewrite', 'headers', 'mime', 'dir' ] as $module ) {
				$config .= "LoadModule {$module}_module /usr/libexec/apache2/mod_$module.so\n";
			}
			$config .= "DocumentRoot $root\nTypesConfig /etc/apache2/mime.types\n<Directory $root>\nRequire all granted\nAllowOverride All\nOptions FollowSymLinks\n</Directory>\n";
			file_put_contents( $root . '/origin.txt', 'WordPress' );
			file_put_contents( $root . '/.htaccess', "RewriteEngine On\nRewriteRule ^origin.txt$ - [L]\nRewriteRule ^ /origin.txt [L]\n" );
			$command = [ $binary, '-f', $root . '/server.conf', '-X' ];
		}
		file_put_contents( $root . '/server.conf', $config );
		$this->process = proc_open(
			$command,
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'file', $root . '/stdout.log', 'a' ],
				2 => [ 'file', $root . '/stderr.log', 'a' ],
			],
			$pipes
		);
		fclose( $pipes[0] );
		for ( $attempt = 0; $attempt < 40; $attempt++ ) {
			$connection = @fsockopen( '127.0.0.1', $this->port );
			if ( is_resource( $connection ) ) {
				fclose( $connection );

				return;
			}
			usleep( 50000 );
		}
		self::fail( file_get_contents( $root . '/stderr.log' ) . ( is_file( $root . '/error.log' ) ? file_get_contents( $root . '/error.log' ) : '' ) );
	}

	private function request( string $url, string $method = 'GET' ): array {
		$handle  = curl_init( 'http://127.0.0.1:' . $this->port . $url );
		$headers = [];
		curl_setopt_array(
			$handle,
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 5,
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_NOBODY         => $method === 'HEAD',
				CURLOPT_HEADERFUNCTION => static function ( mixed $curl, string $line ) use ( &$headers ): int {
					if ( str_contains( $line, ':' ) ) {
						[$key, $value]                         = explode( ':', $line, 2 );
						$headers[ strtolower( trim( $key ) ) ] = trim( $value );
					}

					return strlen( $line );
				},
			]
		);
		$body   = curl_exec( $handle );
		$status = curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );

		return compact( 'status', 'body', 'headers' );
	}

	private function remove( string $path ): void {
		if ( is_link( $path ) || ! is_dir( $path ) ) {
			unlink( $path );

			return;
		}
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $name ) {
			$this->remove( $path . '/' . $name );
		}
		rmdir( $path );
	}
}

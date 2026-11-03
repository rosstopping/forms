<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\DeploymentEndpoint;
use Sitewell\StaticFrontend\DeploymentInProgress;
use Sitewell\StaticFrontend\DeploymentManager;
use Sitewell\StaticFrontend\ReleaseInstaller;
use Sitewell\StaticFrontend\SitewellClient;
use WP_Error;
use WP_REST_Request;
use ZipArchive;

final class DeploymentManagerTest extends TestCase {

	private string $directory;

	private array $requests = [];

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/sitewell-update-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory . '/releases', 0777, true );
		$GLOBALS['sitewell_test_options'] = [
			SettingsPage::OPTION_CONNECTION => [
				'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
				'credential'    => 'swp_test',
				'website'       => [
					'name'   => 'Test website',
					'domain' => 'example.com',
				],
			],
		];
		$GLOBALS['sitewell_test_http']    = function ( string $url, array $arguments ): array {
			$this->requests[] = compact( 'url', 'arguments' );

			return [
				'status' => 204,
				'body'   => '',
			];
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['sitewell_test_http'] );
		$GLOBALS['sitewell_test_options'] = [];
		$this->remove( $this->directory );
	}

	public function test_a_concurrent_update_returns_before_any_network_request(): void {
		$this->withHeldLock(
			'.update.lock',
			function (): void {
				$started = microtime( true );
				try {
					$this->manager()->checkForUpdate();
					self::fail( 'A concurrent update must not start.' );
				} catch ( DeploymentInProgress $exception ) {
					self::assertStringContainsString( 'already in progress', $exception->getMessage() );
				}
				self::assertLessThan( 1, microtime( true ) - $started );
				self::assertSame( [], $this->requests );
				self::assertArrayNotHasKey( SettingsPage::OPTION_ACTIVE_RELEASE, $GLOBALS['sitewell_test_options'] );
			}
		);
	}

	public function test_the_lock_is_already_held_during_the_first_remote_request(): void {
		$attempted                     = false;
		$GLOBALS['sitewell_test_http'] = function ( string $url, array $arguments ) use ( &$attempted ): array {
			$this->requests[] = compact( 'url', 'arguments' );
			if ( ! $attempted ) {
				$attempted = true;
				try {
					$this->manager()->checkForUpdate();
					self::fail( 'The second updater must be rejected before networking.' );
				} catch ( DeploymentInProgress $exception ) {
					self::assertStringContainsString( 'already in progress', $exception->getMessage() );
				}
			}

			return [
				'status' => 204,
				'body'   => '',
			];
		};
		self::assertFalse( $this->manager()->checkForUpdate() );
		self::assertCount( 2, $this->requests );
		self::assertStringEndsWith( '/heartbeat', $this->requests[0]['url'] );
		$heartbeat = json_decode( $this->requests[0]['arguments']['body'], true );
		self::assertSame( SITEWELL_STATIC_FRONTEND_VERSION, $heartbeat['plugin_version'] );
	}

	public function test_a_failed_remote_check_releases_the_lock_for_the_next_attempt(): void {
		$GLOBALS['sitewell_test_http'] = static fn (): WP_Error => new WP_Error( 'network_error', 'Connection failed' );
		try {
			$this->manager()->checkForUpdate();
			self::fail( 'The network error must be reported.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'Connection failed', $exception->getMessage() );
		}
		$GLOBALS['sitewell_test_http'] = static fn (): array => [
			'status' => 204,
			'body'   => '',
		];
		self::assertFalse( $this->manager()->checkForUpdate() );
	}

	public function test_a_busy_webhook_is_accepted_without_starting_another_update(): void {
		$this->withHeldLock(
			'.update.lock',
			function (): void {
				$response = ( new DeploymentEndpoint( $this->manager() ) )->deploy( $this->request() );
				self::assertSame( 202, $response->get_status() );
				self::assertSame(
					[
						'installed'   => false,
						'in_progress' => true,
					],
					$response->get_data()
				);
				self::assertSame( [], $this->requests );
			}
		);
	}

	public function test_a_delivery_change_does_not_leave_an_installer_waiting(): void {
		$this->withHeldLock(
			'.install.lock',
			function (): void {
				$started = microtime( true );
				try {
					( new ReleaseInstaller( $this->directory . '/releases' ) )->install( [], '/unused.zip' );
					self::fail( 'A concurrent installer must not wait.' );
				} catch ( DeploymentInProgress $exception ) {
					self::assertStringContainsString( 'delivery change', $exception->getMessage() );
				}
				self::assertLessThan( 1, microtime( true ) - $started );
			}
		);
	}

	public function test_a_single_update_still_downloads_verifies_installs_and_acknowledges(): void {
		$archivePath = $this->directory . '/release.zip';
		$archive     = new ZipArchive();
		$archive->open( $archivePath, ZipArchive::CREATE );
		$archive->addFromString( 'index.html', '<h1>Updated website</h1>' );
		$archive->close();
		$releaseId                     = 'wsr_abcdefghijklmnopqrstuvwxyz12';
		$temporary                     = null;
		$GLOBALS['sitewell_test_http'] = function ( string $url, array $arguments ) use ( $archivePath, $releaseId, &$temporary ): array {
			$this->requests[] = compact( 'url', 'arguments' );
			if ( str_ends_with( $url, '/releases/current' ) ) {
				return [
					'status' => 200,
					'body'   => json_encode(
						[
							'data' => [
								'release_id'   => $releaseId,
								'commit_sha'   => str_repeat( 'a', 40 ),
								'checksum'     => hash_file( 'sha256', $archivePath ),
								'size'         => filesize( $archivePath ),
								'download_url' => 'https://sitewell.example/api/download',
							],
						]
					),
				];
			}
			if ( isset( $arguments['filename'] ) ) {
				$temporary = $arguments['filename'];
				copy( $archivePath, $temporary );

				return [
					'status' => 200,
					'body'   => '',
				];
			}

			return [
				'status' => 204,
				'body'   => '',
			];
		};
		$response                      = ( new DeploymentEndpoint( $this->manager() ) )->deploy( $this->request() );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( [ 'installed' => true ], $response->get_data() );
		$active = $GLOBALS['sitewell_test_options'][ SettingsPage::OPTION_ACTIVE_RELEASE ];
		self::assertSame( $releaseId, $active['release_id'] );
		self::assertSame( '<h1>Updated website</h1>', file_get_contents( $active['path'] . '/index.html' ) );
		self::assertCount( 4, $this->requests );
		self::assertStringEndsWith( '/activated', $this->requests[3]['url'] );
		self::assertFileDoesNotExist( $temporary );
	}

	private function manager(): DeploymentManager {
		return new DeploymentManager( new SitewellClient( 'https://sitewell.example/api' ), new ReleaseInstaller( $this->directory . '/releases' ), $this->directory . '/releases' );
	}

	private function request(): WP_REST_Request {
		$request = new WP_REST_Request();
		$request->set_param( 'release_id', 'wsr_abcdefghijklmnopqrstuvwxyz12' );

		return $request;
	}

	private function withHeldLock( string $name, callable $test ): void {
		$code    = '$lock=fopen(' . var_export( $this->directory . '/releases/' . $name, true ) . ',"c");flock($lock,LOCK_EX);echo "locked\\n";fflush(STDOUT);sleep(3);fclose($lock);';
		$process = proc_open(
			[ PHP_BINARY, '-r', $code ],
			[
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes
		);
		self::assertIsResource( $process );
		try {
			self::assertSame( "locked\n", fgets( $pipes[1] ) );
			$test();
		} finally {
			proc_terminate( $process );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			proc_close( $process );
		}
	}

	private function remove( string $path ): void {
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $name ) {
			$child = $path . '/' . $name;
			if ( is_dir( $child ) ) {
				$this->remove( $child );
			} else {
				unlink( $child );
			}
		}
		rmdir( $path );
	}
}

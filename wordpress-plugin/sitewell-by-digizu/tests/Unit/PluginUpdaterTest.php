<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sitewell\StaticFrontend\PluginUpdater;

final class PluginUpdaterTest extends TestCase {

	private PluginUpdater $updater;

	private const FILE = 'sitewell-by-digizu/sitewell-static-frontend.php';

	protected function setUp(): void {
		$this->updater                       = new PluginUpdater( self::FILE );
		$GLOBALS['sitewell_test_transients'] = [];
		unset( $GLOBALS['sitewell_test_http'], $GLOBALS['sitewell_test_request'], $GLOBALS['sitewell_test_download_url'] );
		$GLOBALS['sitewell_test_response'] = [
			'status' => 200,
			'body'   => json_encode( $this->metadata() ),
		];
	}

	private function metadata(): array {
		return [
			'version'      => '1.0.6',
			'package'      => PluginUpdater::HOME . '/download?version=1.0.6',
			'sha256'       => hash( 'sha256', 'verified zip bytes' ),
			'requires'     => '6.6',
			'requires_php' => '8.2',
			'tested'       => '7.1',
		];
	}

	public function test_it_provides_native_metadata_without_forcing_auto_updates_or_changing_settings(): void {
		$GLOBALS['sitewell_test_options'] = [
			'sitewell_static_frontend_enabled'    => false,
			'sitewell_static_frontend_connection' => [ 'credential' => 'private' ],
		];
		$before                           = $GLOBALS['sitewell_test_options'];
		$result                           = $this->updater->update( false, [], self::FILE );
		self::assertSame( '1.0.6', $result['version'] );
		self::assertSame( PluginUpdater::HOME, $result['id'] );
		self::assertArrayNotHasKey( 'autoupdate', $result );
		self::assertSame( $before, $GLOBALS['sitewell_test_options'] );
		self::assertSame( PluginUpdater::HOME . '/update.json', $GLOBALS['sitewell_test_request']['url'] );
		self::assertArrayNotHasKey( 'Authorization', $GLOBALS['sitewell_test_request']['arguments']['headers'] );
		$GLOBALS['sitewell_test_response'] = new \WP_Error( 'offline' );
		self::assertSame( $result, $this->updater->update( false, [], self::FILE ) );
		self::assertSame( '1.0.6', $this->updater->information( false, 'plugin_information', (object) [ 'slug' => PluginUpdater::SLUG ] )->version );
	}

	public function test_it_returns_current_version_metadata_so_wordpress_can_offer_auto_update_controls(): void {
		$data                                      = $this->metadata();
		$data['version']                           = '1.0.5';
		$data['package']                           = PluginUpdater::HOME . '/download?version=1.0.5';
		$GLOBALS['sitewell_test_response']['body'] = json_encode( $data );
		self::assertSame( '1.0.5', $this->updater->update( false, [ 'Version' => '1.0.5' ], self::FILE )['version'] );
	}

	public function test_it_ignores_other_plugins_without_network_requests(): void {
		self::assertSame( 'existing', $this->updater->update( 'existing', [], 'other/plugin.php' ) );
		self::assertFalse( $this->updater->information( false, 'plugin_information', (object) [ 'slug' => 'other' ] ) );
		self::assertFalse( $this->updater->download( false, 'https://other.example/plugin.zip' ) );
		self::assertArrayNotHasKey( 'sitewell_test_request', $GLOBALS );
	}

	public function test_it_rejects_failed_malformed_and_untrusted_metadata(): void {
		$untrusted            = $this->metadata();
		$untrusted['package'] = 'https://attacker.example/plugin.zip';
		foreach ( [
			new \WP_Error( 'offline' ),
			[
				'status' => 503,
				'body'   => '{}',
			],
			[
				'status' => 200,
				'body'   => 'not json',
			],
			[
				'status' => 200,
				'body'   => json_encode( $untrusted ),
			],
		] as $response ) {
			$GLOBALS['sitewell_test_transients'] = [];
			$GLOBALS['sitewell_test_response']   = $response;
			self::assertFalse( $this->updater->update( false, [], self::FILE ) );
		}
	}

	public function test_it_verifies_downloaded_bytes_and_rejects_corruption(): void {
		foreach ( [ 'verified zip bytes', 'corrupt' ] as $bytes ) {
			$file = tempnam( sys_get_temp_dir(), 'sitewell-update-' );
			file_put_contents( $file, $bytes );
			$GLOBALS['sitewell_test_download'] = $file;
			$result                            = $this->updater->download( false, $this->metadata()['package'] );
			if ( $bytes === 'corrupt' ) {
				self::assertInstanceOf( \WP_Error::class, $result );
				self::assertFileDoesNotExist( $file );
			} else {
				self::assertSame( $file, $result );
				unlink( $file );
			}
		}
	}

	public function test_it_rejects_a_stale_release_and_preserves_download_errors(): void {
		self::assertInstanceOf( \WP_Error::class, $this->updater->download( false, PluginUpdater::HOME . '/download?version=1.0.3' ) );
		self::assertArrayNotHasKey( 'sitewell_test_download_url', $GLOBALS );
		$error                             = new \WP_Error( 'download failed' );
		$GLOBALS['sitewell_test_download'] = $error;
		self::assertSame( $error, $this->updater->download( false, $this->metadata()['package'] ) );
	}
}

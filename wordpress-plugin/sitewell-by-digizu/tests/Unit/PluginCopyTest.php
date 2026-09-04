<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginCopyTest extends TestCase {

	public function test_it_uses_customer_friendly_plugin_copy(): void {
		$plugin   = file_get_contents( dirname( __DIR__, 2 ) . '/sitewell-static-frontend.php' );
		$settings = file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/SettingsPage.php' );

		self::assertIsString( $plugin );
		self::assertIsString( $settings );
		self::assertStringContainsString( 'Plugin Name: Sitewell by Digizu', $plugin );
		self::assertStringContainsString( 'Version: 1.0.0', $plugin );
		self::assertStringContainsString( 'License: GPLv2 or later', $plugin );
		self::assertStringContainsString( "__( 'Check for updates', 'sitewell-static-frontend' )", $settings );
		self::assertStringContainsString( "submit_button( __( 'Save', 'sitewell-static-frontend' ) )", $settings );
		self::assertStringNotContainsString( 'Sitewell Static Frontend', $settings );
	}

	public function test_it_documents_the_sitewell_service_for_wordpress_org(): void {
		$readme = file_get_contents( dirname( __DIR__, 2 ) . '/readme.txt' );

		self::assertIsString( $readme );
		self::assertStringContainsString( 'Contributors: digizu', $readme );
		self::assertStringContainsString( 'Stable tag: 1.0.0', $readme );
		self::assertStringContainsString( '== External service ==', $readme );
		self::assertStringContainsString( 'https://sitewell.digizu.co.uk/privacy-policy', $readme );
		self::assertStringContainsString( 'https://sitewell.digizu.co.uk/terms-of-service', $readme );
		self::assertStringContainsString( 'sitewell@digizu.co.uk', $readme );
	}
}

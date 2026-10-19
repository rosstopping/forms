<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewell\StaticFrontend\Admin\SettingsPage;
use Sitewell\StaticFrontend\RobotsPublisher;

final class RobotsPublisherTest extends TestCase {

	private string $root;

	private string $release;

	private RobotsPublisher $publisher;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/sitewell-robots-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root );
		$this->release = $this->root . '/release';
		mkdir( $this->release );
		$this->publisher                  = new RobotsPublisher( $this->root, $this->root . '/locks' );
		$GLOBALS['sitewell_test_options'] = [];
		file_put_contents( $this->release . '/robots.txt', "User-agent: *\nSitemap: https://example.com/sitemap.xml\n" );
	}

	protected function tearDown(): void {
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $this->root );
	}

	public function test_updates_keep_original_bytes_and_permissions_for_restoration(): void {
		$original = "Sitemap: old.xml\r\n";
		file_put_contents( $this->root . '/robots.txt', $original );
		chmod( $this->root . '/robots.txt', 0640 );
		$this->publisher->synchronize( $this->release );
		self::assertSame( file_get_contents( $this->release . '/robots.txt' ), file_get_contents( $this->root . '/robots.txt' ) );
		$this->publisher->synchronize( $this->release );
		file_put_contents( $this->release . '/robots.txt', 'new release' );
		$this->publisher->synchronize( $this->release );
		$this->publisher->restore();
		self::assertSame( $original, file_get_contents( $this->root . '/robots.txt' ) );
		self::assertSame( 0640, fileperms( $this->root . '/robots.txt' ) & 0777 );
		self::assertNull( get_option( RobotsPublisher::OPTION, null ) );
	}

	public function test_it_removes_its_file_if_no_original_existed(): void {
		$this->publisher->synchronize( $this->release );
		$this->publisher->restore();
		self::assertFileDoesNotExist( $this->root . '/robots.txt' );
		$this->publisher->restore();
	}

	public function test_release_without_robots_restores_original(): void {
		file_put_contents( $this->root . '/robots.txt', 'original' );
		$this->publisher->synchronize( $this->release );
		unlink( $this->release . '/robots.txt' );
		$this->publisher->synchronize( $this->release );
		self::assertSame( 'original', file_get_contents( $this->root . '/robots.txt' ) );
	}

	public function test_disabled_sites_do_not_publish_during_installation(): void {
		$this->publisher->synchronize( $this->release, true );
		self::assertFileDoesNotExist( $this->root . '/robots.txt' );
		update_option( SettingsPage::OPTION_ENABLED, true );
		$this->publisher->synchronize( $this->release, true );
		self::assertFileExists( $this->root . '/robots.txt' );
	}

	public function test_external_edits_are_preserved_on_update_and_restore(): void {
		file_put_contents( $this->root . '/robots.txt', 'original' );
		$this->publisher->synchronize( $this->release );
		file_put_contents( $this->root . '/robots.txt', 'external' );
		foreach ( [ fn () => $this->publisher->synchronize( $this->release ), fn () => $this->publisher->restore() ] as $operation ) {
			try {
				$operation();
				self::fail( 'Expected a conflict' );
			} catch ( RuntimeException $exception ) {
				self::assertStringContainsString( 'outside Sitewell', $exception->getMessage() );
			}
		}
		self::assertSame( 'external', file_get_contents( $this->root . '/robots.txt' ) );
		self::assertIsArray( get_option( RobotsPublisher::OPTION ) );
	}

	public function test_symlinks_are_rejected_without_touching_the_destination(): void {
		file_put_contents( $this->root . '/other.txt', 'untouched' );
		symlink( $this->root . '/other.txt', $this->root . '/robots.txt' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Symlinks' );
		try {
			$this->publisher->synchronize( $this->release );
		} finally {
			self::assertSame( 'untouched', file_get_contents( $this->root . '/other.txt' ) );
		}
	}

	public function test_permission_failure_preserves_original_and_allows_retry(): void {
		file_put_contents( $this->root . '/robots.txt', 'original' );
		mkdir( $this->root . '/locks' );
		chmod( $this->root, 0555 );
		try {
			$this->publisher->synchronize( $this->release );
			self::fail( 'Expected permission failure' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'permissions', $exception->getMessage() );
		} finally {
			chmod( $this->root, 0755 );
		}
		self::assertSame( 'original', file_get_contents( $this->root . '/robots.txt' ) );
		$this->publisher->synchronize( $this->release );
		$this->publisher->restore();
		self::assertSame( 'original', file_get_contents( $this->root . '/robots.txt' ) );
	}
}

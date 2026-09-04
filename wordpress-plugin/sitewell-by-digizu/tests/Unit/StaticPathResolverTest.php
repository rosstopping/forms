<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sitewell\StaticFrontend\StaticPathResolver;

final class StaticPathResolverTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/sitewell-static-resolver-' . bin2hex( random_bytes( 8 ) );

		mkdir( $this->root . '/services/nested', 0777, true );
		mkdir( $this->root . '/assets', 0777, true );
		file_put_contents( $this->root . '/index.html', 'home' );
		file_put_contents( $this->root . '/services/index.html', 'services' );
		file_put_contents( $this->root . '/services/nested/index.html', 'nested' );
		file_put_contents( $this->root . '/single.html', 'single' );
		file_put_contents( $this->root . '/404.html', 'missing' );
		file_put_contents( $this->root . '/assets/site.css', 'body{}' );
		file_put_contents( $this->root . '/danger.php', '<?php echo "unsafe";' );
		file_put_contents( $this->root . '/.env', 'secret' );
	}

	protected function tearDown(): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $this->root );
	}

	#[DataProvider( 'validPathProvider' )]
	public function test_it_resolves_static_paths( string $uri, string $expectedSuffix, string $contentType ): void {
		$resolved = ( new StaticPathResolver( $this->root ) )->resolve( $uri );

		self::assertNotNull( $resolved );
		self::assertStringEndsWith( $expectedSuffix, $resolved->path );
		self::assertSame( $contentType, $resolved->contentType );
	}

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function validPathProvider(): iterable {
		yield 'root' => [ '/', '/index.html', 'text/html; charset=UTF-8' ];
		yield 'query string' => [ '/?campaign=test', '/index.html', 'text/html; charset=UTF-8' ];
		yield 'directory index' => [ '/services/', '/services/index.html', 'text/html; charset=UTF-8' ];
		yield 'nested directory index' => [ '/services/nested/', '/services/nested/index.html', 'text/html; charset=UTF-8' ];
		yield 'html fallback' => [ '/single/', '/single.html', 'text/html; charset=UTF-8' ];
		yield 'asset' => [ '/assets/site.css?v=1', '/assets/site.css', 'text/css; charset=UTF-8' ];
	}

	#[DataProvider( 'unsafePathProvider' )]
	public function test_it_rejects_unsafe_and_missing_paths( string $uri ): void {
		self::assertNull( ( new StaticPathResolver( $this->root ) )->resolve( $uri ) );
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function unsafePathProvider(): iterable {
		yield 'missing' => [ '/missing/' ];
		yield 'parent traversal' => [ '/../index.html' ];
		yield 'encoded traversal' => [ '/%2e%2e/index.html' ];
		yield 'double encoded traversal' => [ '/%252e%252e/index.html' ];
		yield 'encoded slash traversal' => [ '/%2e%2e%2findex.html' ];
		yield 'backslash traversal' => [ '/..\\index.html' ];
		yield 'encoded backslash' => [ '/%2e%2e%5cindex.html' ];
		yield 'null byte' => [ "/index.html\0.php" ];
		yield 'encoded null byte' => [ '/index.html%00.php' ];
		yield 'php file' => [ '/danger.php' ];
		yield 'dotfile' => [ '/.env' ];
	}

	public function test_it_returns_the_static404_document(): void {
		$fallback = ( new StaticPathResolver( $this->root ) )->fallback404();

		self::assertNotNull( $fallback );
		self::assertStringEndsWith( '/404.html', $fallback->path );
	}

	public function test_it_rejects_symlinks_that_escape_the_static_root(): void {
		$outside = tempnam( sys_get_temp_dir(), 'sitewell-outside-' );
		self::assertNotFalse( $outside );
		file_put_contents( $outside, 'outside' );

		if ( ! symlink( $outside, $this->root . '/outside.html' ) ) {
			unlink( $outside );
			self::markTestSkipped( 'The environment does not permit symlinks.' );
		}

		self::assertNull( ( new StaticPathResolver( $this->root ) )->resolve( '/outside.html' ) );

		unlink( $this->root . '/outside.html' );
		unlink( $outside );
	}
}

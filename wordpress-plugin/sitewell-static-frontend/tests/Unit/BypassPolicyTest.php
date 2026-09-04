<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sitewell\StaticFrontend\BypassPolicy;

final class BypassPolicyTest extends TestCase {

	#[DataProvider( 'internalRequestProvider' )]
	public function test_it_bypasses_internal_word_press_requests( string $uri ): void {
		self::assertTrue( ( new BypassPolicy() )->shouldBypass( $uri ) );
	}

	/** @return iterable<string, array{string}> */
	public static function internalRequestProvider(): iterable {
		yield 'admin' => [ '/wp-admin/' ];
		yield 'nested admin' => [ '/wp-admin/options-general.php?page=sitewell-static-frontend' ];
		yield 'login' => [ '/wp-login.php?redirect_to=%2Fwp-admin%2F' ];
		yield 'REST index' => [ '/wp-json/' ];
		yield 'REST namespace' => [ '/wp-json/wp/v2/pages' ];
		yield 'cron' => [ '/wp-cron.php' ];
		yield 'comments' => [ '/wp-comments-post.php' ];
		yield 'XML-RPC' => [ '/xmlrpc.php' ];
	}

	public function test_it_allows_public_get_and_head_requests(): void {
		$policy = new BypassPolicy();

		self::assertFalse( $policy->shouldBypass( '/services/', 'GET' ) );
		self::assertFalse( $policy->shouldBypass( '/services/', 'HEAD' ) );
	}

	public function test_it_bypasses_methods_that_may_mutate_word_press(): void {
		$policy = new BypassPolicy();

		self::assertTrue( $policy->shouldBypass( '/contact/', 'POST' ) );
		self::assertTrue( $policy->shouldBypass( '/anything/', 'PUT' ) );
		self::assertTrue( $policy->shouldBypass( '/anything/', 'DELETE' ) );
	}

	#[DataProvider( 'runtimeBypassProvider' )]
	public function test_it_bypasses_word_press_runtime_contexts( array $runtime ): void {
		self::assertTrue( ( new BypassPolicy() )->shouldBypass( '/', 'GET', ...$runtime ) );
	}

	/** @return iterable<string, array{array{isAdmin?: bool, isAjax?: bool, isCron?: bool, isRest?: bool, isCli?: bool}}> */
	public static function runtimeBypassProvider(): iterable {
		yield 'admin' => [ [ 'isAdmin' => true ] ];
		yield 'AJAX' => [ [ 'isAjax' => true ] ];
		yield 'cron' => [ [ 'isCron' => true ] ];
		yield 'REST' => [ [ 'isRest' => true ] ];
		yield 'WP-CLI' => [ [ 'isCli' => true ] ];
	}
}

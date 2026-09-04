<?php

declare(strict_types=1);

namespace Sitewell\StaticFrontend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sitewell\StaticFrontend\SitewellClient;

final class SitewellClientTest extends TestCase
{

    protected function setUp(): void
    {
        $GLOBALS['sitewell_test_request']  = null;
        $GLOBALS['sitewell_test_response'] = [
        'status' => 201,
        'body'   => json_encode(
            [
            'data' => [
            'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
            'credential'    => 'swp_' . str_repeat('a', 64),
            'website'       => [
            'name'   => 'Example Website',
            'domain' => 'example.com',
            ],
            ],
            ],
            JSON_THROW_ON_ERROR
        ),
        ];
    }

    public function test_it_exchanges_a_connection_code_without_sending_git_hub_credentials(): void
    {
        $connection = ( new SitewellClient('https://sitewell.example/api/') )->connect('ABCD-EFGH-IJKL');

        self::assertSame('wpc_abcdefghijklmnopqrstuvwxyz12', $connection['connection_id']);
        self::assertSame('Example Website', $connection['website']['name']);
        self::assertSame('https://sitewell.example/api/wordpress/connections', $GLOBALS['sitewell_test_request']['url']);
        self::assertSame('POST', $GLOBALS['sitewell_test_request']['arguments']['method']);
        self::assertArrayNotHasKey('Authorization', $GLOBALS['sitewell_test_request']['arguments']['headers']);

        $body = json_decode($GLOBALS['sitewell_test_request']['arguments']['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ABCD-EFGH-IJKL', $body['code']);
        self::assertSame('https://example.com/', $body['site_url']);
    }

    public function test_it_authenticates_heartbeat_requests_with_the_installation_credential(): void
    {
        $GLOBALS['sitewell_test_response'] = [
        'status' => 204,
        'body'   => '',
        ];
        $connection                        = [
        'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
        'credential'    => 'swp_' . str_repeat('b', 64),
        ];

        ( new SitewellClient('https://sitewell.example/api') )->heartbeat($connection);

        self::assertSame('POST', $GLOBALS['sitewell_test_request']['arguments']['method']);
        self::assertSame('Bearer ' . $connection['credential'], $GLOBALS['sitewell_test_request']['arguments']['headers']['Authorization']);
        self::assertStringEndsWith('/wordpress/connections/' . $connection['connection_id'] . '/heartbeat', $GLOBALS['sitewell_test_request']['url']);
    }

    public function test_disconnect_treats_an_already_revoked_credential_as_disconnected(): void
    {
        $GLOBALS['sitewell_test_response'] = [
        'status' => 401,
        'body'   => '{"message":"Invalid WordPress connection credentials."}',
        ];
        $connection                        = [
        'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
        'credential'    => 'swp_' . str_repeat('b', 64),
        ];

        ( new SitewellClient('https://sitewell.example/api') )->disconnect($connection);

        self::assertSame('DELETE', $GLOBALS['sitewell_test_request']['arguments']['method']);
    }

    public function test_it_retrieves_the_latest_static_release(): void
    {
        $GLOBALS['sitewell_test_response'] = [
        'status' => 200,
        'body'   => json_encode(
            [
            'data' => [
            'release_id'   => 'wsr_abcdefghijklmnopqrstuvwxyz12',
            'commit_sha'   => str_repeat('a', 40),
            'checksum'     => str_repeat('b', 64),
            'size'         => 1234,
            'download_url' => 'https://sitewell.example/api/wordpress/connections/wpc/releases/wsr/download',
            ],
            ],
            JSON_THROW_ON_ERROR,
        ),
        ];
        $connection                        = [
        'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
        'credential'    => 'swp_' . str_repeat('b', 64),
        ];

        $release = ( new SitewellClient('https://sitewell.example/api') )->currentRelease($connection, 'wsr_previous');

        self::assertSame('wsr_abcdefghijklmnopqrstuvwxyz12', $release['release_id']);
        self::assertStringContainsString('active_release=wsr_previous', $GLOBALS['sitewell_test_request']['url']);
        self::assertSame('Bearer ' . $connection['credential'], $GLOBALS['sitewell_test_request']['arguments']['headers']['Authorization']);
    }

    public function test_it_returns_no_release_for_a_no_content_response(): void
    {
        $GLOBALS['sitewell_test_response'] = [
        'status' => 204,
        'body'   => '',
        ];
        $connection                        = [
        'connection_id' => 'wpc_abcdefghijklmnopqrstuvwxyz12',
        'credential'    => 'swp_' . str_repeat('b', 64),
        ];

        self::assertNull(( new SitewellClient('https://sitewell.example/api') )->currentRelease($connection, null));
    }

    public function test_it_rejects_invalid_successful_responses(): void
    {
        $GLOBALS['sitewell_test_response'] = [
        'status' => 201,
        'body'   => '{"data":{"connection_id":"unexpected"}}',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sitewell returned an invalid connection response.');

        ( new SitewellClient('https://sitewell.example/api') )->connect('ABCD-EFGH-IJKL');
    }
}

<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @return array{body: string, headers: array<string, string>, status: int, cache_disabled: bool} */
function wordpressStaticResponse(string $uri, string $method = 'GET', bool $enabled = true): array
{
    $directory = sys_get_temp_dir().'/sitewell-routing-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $pluginPath = dirname(__DIR__, 2).'/wordpress-plugin/sitewell-by-digizu';
    $script = <<<'SCRIPT'
namespace Sitewell\StaticFrontend {
    function header(string $header): void {
        [$name, $value] = explode(': ', $header, 2);
        $GLOBALS['response_headers'][$name] = $value;
    }
}
namespace {
    require $argv[1].'/tests/bootstrap.php';
    foreach (['FrontendRouter', 'DeploymentManager', 'DeploymentEndpoint', 'Admin/ConnectionActions', 'Plugin'] as $class) {
        require $argv[1].'/src/'.$class.'.php';
    }
    define('SITEWELL_STATIC_FRONTEND_PATH', $argv[1].'/');
    define('SITEWELL_STATIC_FRONTEND_API_URL', 'https://sitewell.example/api');
    define('MINUTE_IN_SECONDS', 60);
    function is_admin(): bool { return false; }
    function wp_upload_dir(): array { return ['basedir' => $GLOBALS['argv'][2]]; }
    function wp_next_scheduled(string $hook): int { return 1; }
    function add_action(string $hook, mixed $callback, int $priority = 10): void {
        $GLOBALS['hooks'][$hook][$priority][] = $callback;
    }
    function add_filter(string $hook, mixed $callback, int $priority = 10): void {
        add_action($hook, $callback, $priority);
    }
    function status_header(int $status): void { $GLOBALS['response_status'] = $status; }
    function nocache_headers(): void { $GLOBALS['response_headers']['Cache-Control'] = 'no-cache'; }
    $GLOBALS['response_status'] = 404;
    $GLOBALS['response_headers'] = [];
    $_SERVER['REQUEST_URI'] = $argv[3];
    $_SERVER['REQUEST_METHOD'] = $argv[4];
    $archivePath = $argv[2].'/artifact.zip';
    $zip = new \ZipArchive;
    $zip->open($archivePath, \ZipArchive::CREATE);
    foreach ([
        'index.html' => '<h1>Home</h1>',
        'plumbing/index.html' => '<section class="hero"><h1>Plumbing Work in Doncaster</h1></section>',
        'assets/site.css' => 'body{color:teal}',
        'sitemap.xml' => '<?xml version="1.0"?><urlset><url><loc>https://rowglo.co.uk/plumbing/</loc></url></urlset>',
        'robots.txt' => "User-agent: *\nSitemap: https://rowglo.co.uk/sitemap.xml\n",
        '404.html' => '<h1>Not found</h1>',
    ] as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
    (new \Sitewell\StaticFrontend\ReleaseInstaller($argv[2].'/releases'))->install([
        'release_id' => 'wsr_abcdefghijklmnopqrstuvwxyz12',
        'checksum' => hash_file('sha256', $archivePath),
        'size' => filesize($archivePath),
    ], $archivePath);
    update_option(\Sitewell\StaticFrontend\Admin\SettingsPage::OPTION_ENABLED, $argv[5] === '1');
    \Sitewell\StaticFrontend\Plugin::instance()->boot();
    ob_start();
    register_shutdown_function(function (): void {
        $body = ob_get_clean();
        echo json_encode([
            'body' => $body,
            'headers' => $GLOBALS['response_headers'],
            'status' => $GLOBALS['response_status'],
            'cache_disabled' => defined('DONOTCACHEPAGE') && DONOTCACHEPAGE,
        ], JSON_THROW_ON_ERROR);
    });
    // Model early SEO handling and WordPress's later robots/template handlers.
    add_action('parse_request', static function (): void { echo 'WordPress handler'; exit; }, 0);
    ksort($GLOBALS['hooks']['parse_request']);
    foreach ($GLOBALS['hooks']['parse_request'] as $callbacks) {
        foreach ($callbacks as $callback) { $callback(); }
    }
}
SCRIPT;
    try {
        $process = new Process([PHP_BINARY, '-r', $script, $pluginPath, $directory, $uri, $method, $enabled ? '1' : '0']);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
}

it('serves installed static files before WordPress routing with explicit response headers', function (string $uri, string $body, string $contentType): void {
    $response = wordpressStaticResponse($uri);

    expect($response['status'])->toBe(200)
        ->and($response['body'])->toBe($body)
        ->and($response['headers']['Content-Type'])->toBe($contentType)
        ->and($response['headers']['Cache-Control'])->toContain('no-store')
        ->and($response['headers']['X-Content-Type-Options'])->toBe('nosniff')
        ->and($response['cache_disabled'])->toBeTrue();
})->with([
    'rendered service' => ['/plumbing/', '<section class="hero"><h1>Plumbing Work in Doncaster</h1></section>', 'text/html; charset=UTF-8'],
    'asset' => ['/assets/site.css?v=2', 'body{color:teal}', 'text/css; charset=UTF-8'],
    'sitemap' => ['/sitemap.xml', '<?xml version="1.0"?><urlset><url><loc>https://rowglo.co.uk/plumbing/</loc></url></urlset>', 'application/xml; charset=UTF-8'],
    'robots' => ['/robots.txt', "User-agent: *\nSitemap: https://rowglo.co.uk/sitemap.xml\n", 'text/plain; charset=UTF-8'],
]);

it('serves HEAD responses with the same status and content type but no body', function (string $uri): void {
    $get = wordpressStaticResponse($uri);
    $head = wordpressStaticResponse($uri, 'HEAD');
    expect($head['body'])->toBe('')
        ->and($head['status'])->toBe($get['status'])
        ->and($head['headers'])->toBe($get['headers']);
})->with(['/plumbing/', '/sitemap.xml', '/robots.txt', '/assets/site.css']);

it('keeps the static 404 status and body', function (): void {
    $response = wordpressStaticResponse('/missing/');
    expect($response['status'])->toBe(404)->and($response['body'])->toBe('<h1>Not found</h1>');
});

it('preserves WordPress operational requests and disabled sites', function (string $uri, string $method, bool $enabled): void {
    $response = wordpressStaticResponse($uri, $method, $enabled);
    expect($response['body'])->toBe('WordPress handler')->and($response['cache_disabled'])->toBeFalse();
})->with([
    'admin' => ['/wp-admin/', 'GET', true],
    'REST' => ['/wp-json/sitewell-static-frontend/v1/deploy', 'GET', true],
    'plain REST' => ['/?rest_route=/wp/v2/pages', 'GET', true],
    'cron' => ['/wp-cron.php', 'GET', true],
    'login' => ['/wp-login.php', 'GET', true],
    'POST' => ['/contact/', 'POST', true],
    'disabled' => ['/sitemap.xml', 'GET', false],
]);

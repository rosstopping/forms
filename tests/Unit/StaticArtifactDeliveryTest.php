<?php

use Illuminate\Filesystem\Filesystem;
use Sitewell\StaticFrontend\StaticArtifactValidator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2).'/wordpress-plugin/sitewell-by-digizu/src/StaticArtifactValidator.php';

/** @return array<string, string> */
function optimizedDeliveryFiles(string $colour = 'teal'): array
{
    $font = 'wOF2'.str_repeat('font bytes', 20);
    $fontUrl = '/assets/fonts/lato.'.substr(hash('sha256', $font), 0, 16).'.woff2';
    $css = '@font-face{font-family:Lato;src:url("'.$fontUrl.'")}body{color:'.$colour.'}';
    $cssUrl = '/assets/static/site.'.substr(hash('sha256', $css), 0, 16).'.css';

    return [
        'index.html' => '<html><head><link rel="stylesheet" href="'.$cssUrl.'"></head><body>'.$colour.'</body></html>',
        'service/index.html' => '<h1>'.$colour.'</h1>',
        ltrim($cssUrl, '/') => $css,
        ltrim($fontUrl, '/') => $font,
        'legacy.css' => 'body{}',
        'missing.css/index.html' => '<h1>This is not an asset</h1>',
        '_headers' => "/\n  Cache-Control: public, max-age=0, must-revalidate\n",
        'static-build-manifest.json' => json_encode(['version' => 1, 'pages' => 2, 'assets' => ['/site.css' => $cssUrl, '/font.ttf' => $fontUrl]], JSON_THROW_ON_ERROR),
    ];
}

it('rejects incomplete optimized releases and unresolved dependencies', function (string $mutation): void {
    $files = optimizedDeliveryFiles();
    $manifest = json_decode($files['static-build-manifest.json'], true);
    match ($mutation) {
        'source' => $files = ['index.html' => '{% include source %}'],
        'missing font' => $files = array_diff_key($files, [ltrim($manifest['assets']['/font.ttf'], '/') => true]),
        'wrong bytes' => $files[ltrim($manifest['assets']['/site.css'], '/')] = 'corrupt',
        'missing image' => $files['index.html'] .= '<img src="/missing.png">',
        'remote font' => $files['index.html'] .= '<style>@font-face{src:url(https://fonts.gstatic.com/font.woff2)}</style>',
        'missing css import' => $files['legacy.css'] = '@import "missing.css";',
        'unbuilt page' => $files['service/index.html'] = "---\ntitle: Test\n---\nHello",
    };
    if ($mutation === 'missing css import') {
        $files['index.html'] .= '<link rel="stylesheet" href="/legacy.css">';
    }
    expect(fn () => (new StaticArtifactValidator)->validate(array_keys($files), fn (string $path): string|false => $files[$path] ?? false))
        ->toThrow(RuntimeException::class);
})->with(['source', 'missing font', 'wrong bytes', 'missing image', 'remote font', 'missing css import', 'unbuilt page']);

it('accepts compiled releases with either or both metadata files absent', function (array $missing): void {
    $files = array_diff_key(optimizedDeliveryFiles(), array_fill_keys($missing, true));
    (new StaticArtifactValidator)->validate(array_keys($files), fn (string $path): string|false => $files[$path] ?? false);
    expect(true)->toBeTrue();
})->with([
    'no headers' => [['_headers']],
    'no manifest' => [['static-build-manifest.json']],
    'neither' => [['_headers', 'static-build-manifest.json']],
]);

it('rejects invalid supplied metadata and corrupt fingerprints even without metadata', function (string $mutation): void {
    $files = optimizedDeliveryFiles();
    unset($files['_headers']);
    if ($mutation === 'invalid manifest') {
        $files['static-build-manifest.json'] = '{invalid';
    } else {
        $manifest = json_decode($files['static-build-manifest.json'], true);
        unset($files['static-build-manifest.json']);
        $files[ltrim($manifest['assets']['/site.css'], '/')] = 'corrupt';
    }
    expect(fn () => (new StaticArtifactValidator)->validate(array_keys($files), fn (string $path): string|false => $files[$path] ?? false))
        ->toThrow(RuntimeException::class);
})->with(['invalid manifest', 'corrupt fingerprint']);

it('accepts Google Fonts through HTML and CSS without fetching remote dependencies', function (string $url): void {
    $files = [
        'index.html' => '<link rel="stylesheet" href="'.htmlspecialchars($url).'"><link rel="stylesheet" href="/style.css">',
        'style.css' => '@import "'.$url.'";',
    ];
    (new StaticArtifactValidator)->validate(array_keys($files), fn (string $path): string|false => $files[$path] ?? false);
    expect(true)->toBeTrue();
})->with([
    'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap',
    'https://fonts.googleapis.com/css?family=Montserrat',
    'https://fonts.gstatic.com/s/lato/v24/example.woff2',
]);

it('still rejects other remote fonts and misleading Google Fonts URLs', function (string $url): void {
    $files = ['index.html' => '<style>@font-face{src:url('.$url.')}</style>'];
    expect(fn () => (new StaticArtifactValidator)->validate(array_keys($files), fn (string $path): string|false => $files[$path] ?? false))
        ->toThrow(RuntimeException::class);
})->with([
    'http://fonts.googleapis.com/css2?family=Lato',
    'https://fonts.googleapis.com.example.com/css2?family=Lato',
    'https://fonts.gstatic.com@example.com/s/font.woff2',
    'https://example.com/font.woff2',
    '/wp-content/fonts/google/missing.woff2',
]);

/** @param array<string, string> $files */
function installDeliveryFixture(string $directory, array $files, string $releaseId): void
{
    $archivePath = $directory.'/artifact.zip';
    $zip = new ZipArchive;
    $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $path => $contents) {
        $zip->addFromString($path, $contents);
    }
    $zip->close();
    $script = <<<'SCRIPT'
require $argv[1].'/tests/bootstrap.php';
function wp_delete_file(string $path): void { unlink($path); }
(new \Sitewell\StaticFrontend\ReleaseInstaller($argv[2].'/releases', $argv[2].'/public'))->install([
    'release_id' => $argv[3], 'checksum' => hash_file('sha256', $argv[2].'/artifact.zip'), 'size' => filesize($argv[2].'/artifact.zip'),
], $argv[2].'/artifact.zip');
SCRIPT;
    (new Process([PHP_BINARY, '-r', $script, dirname(__DIR__, 2).'/wordpress-plugin/sitewell-by-digizu', $directory, $releaseId]))->mustRun();
}

it('serves cold and cached assets without WordPress and switches complete releases atomically', function (): void {
    $nginx = trim((new Process(['sh', '-c', 'command -v nginx']))->mustRun()->getOutput());
    $directory = sys_get_temp_dir().'/sitewell-delivery-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $ports = [];
    for ($i = 0; $i < 3; $i++) {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $ports[] = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
    }
    [$originPort, $cachePort, $wordpressPort] = $ports;
    $server = null;
    try {
        $files = optimizedDeliveryFiles();
        installDeliveryFixture($directory, array_diff_key($files, ['static-build-manifest.json' => true, '_headers' => true]), 'wsr_abcdefghijklmnopqrstuvwxyz12');
        $routing = str_replace(['__PUBLIC_PATH__', '__WORDPRESS_ORIGIN__'], [$directory.'/public', 'http://127.0.0.1:'.$wordpressPort], file_get_contents(dirname(__DIR__, 2).'/wordpress-plugin/sitewell-by-digizu/templates/static-nginx.conf'));
        $configuration = <<<CONF
pid $directory/nginx.pid;
error_log $directory/error.log;
worker_processes 1;
events { worker_connections 128; }
http {
    access_log off;
    client_body_temp_path $directory/client;
    proxy_temp_path $directory/proxy;
    proxy_cache_path $directory/cache keys_zone=assets:1m;
    types { text/html html; text/css css; font/woff2 woff2; }
    server { listen 127.0.0.1:$wordpressPort; location / { return 200 "WORDPRESS"; } }
    server { listen 127.0.0.1:$originPort; $routing }
    server {
        listen 127.0.0.1:$cachePort;
        location / {
            proxy_pass http://127.0.0.1:$originPort;
            proxy_cache assets;
            proxy_cache_revalidate on;
            add_header X-Test-Cache \$upstream_cache_status always;
        }
    }
}
CONF;
        file_put_contents($directory.'/nginx.conf', $configuration);
        (new Process([$nginx, '-e', $directory.'/error.log', '-t', '-p', $directory, '-c', $directory.'/nginx.conf']))->mustRun();
        $server = new Process([$nginx, '-e', $directory.'/error.log', '-p', $directory, '-c', $directory.'/nginx.conf', '-g', 'daemon off;']);
        $server->start();
        $request = function (string $uri, int $port, array $headers = [], bool $head = false): array {
            $handle = curl_init('http://127.0.0.1:'.$port.$uri);
            $responseHeaders = [];
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_NOBODY => $head, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                    if (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($line);
                },
            ]);
            $body = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return compact('body', 'status', 'responseHeaders');
        };
        for ($attempt = 0; $attempt < 40; $attempt++) {
            if ($request('/', $originPort)['status'] === 200) {
                break;
            }
            usleep(25000);
        }
        $manifest = json_decode($files['static-build-manifest.json'], true);
        foreach ($manifest['assets'] as $uri) {
            $cold = $request($uri, $cachePort);
            $hit = $request($uri, $cachePort);
            expect($cold['status'])->toBe(200)->and($cold['body'])->toBe($files[ltrim($uri, '/')])
                ->and($cold['responseHeaders']['x-test-cache'])->toBe('MISS')
                ->and($hit['responseHeaders']['x-test-cache'])->toBe('HIT')
                ->and($hit['body'])->toBe($cold['body'])
                ->and($cold['responseHeaders']['content-type'])->toBe(str_ends_with($uri, '.css') ? 'text/css' : 'font/woff2')
                ->and($cold['responseHeaders']['cache-control'])->toBe('public, max-age=31536000, immutable')
                ->and($cold['responseHeaders'])->not->toHaveKey('link');
            $head = $request($uri, $originPort, [], true);
            expect($head['status'])->toBe(200)->and($head['body'])->toBe('');
            $conditional = $request($uri, $originPort, ['If-None-Match: '.$head['responseHeaders']['etag']]);
            expect($conditional['status'])->toBe(304);
        }
        foreach (['/missing.css', '/wp-content/fonts/google/missing.ttf', '/assets/fonts/missing.aaaaaaaaaaaaaaaa.woff2', '/_headers', '/other.php'] as $uri) {
            foreach ([1, 2] as $attempt) {
                $response = $request($uri, $cachePort);
                expect($response['status'])->toBe(404)->and($response['body'])->not->toContain('WORDPRESS')
                    ->and($response['responseHeaders']['cache-control'])->toBe('no-store');
            }
        }
        foreach (['/', '/service', '/service/', '/service/index.html', '/legacy.css'] as $uri) {
            $response = $request($uri, $originPort);
            expect($response['status'])->toBe(200)->and($response['responseHeaders']['cache-control'])->toBe('public, max-age=0, must-revalidate');
        }
        foreach (['/wp-admin/', '/wp-json/test', '/wp-login.php', '/?rest_route=/wp/v2/pages'] as $uri) {
            expect($request($uri, $originPort)['body'])->toBe('WORDPRESS');
        }
        $oldHtml = $request('/', $originPort);
        $newFiles = optimizedDeliveryFiles('blue');
        installDeliveryFixture($directory, $newFiles, 'wsr_abcdefghijklmnopqrstuvwxyz34');
        expect($request('/', $originPort)['body'])->toBe($newFiles['index.html'])
            ->and($request($manifest['assets']['/site.css'], $originPort)['body'])->toBe($files[ltrim($manifest['assets']['/site.css'], '/')]);
        $revalidated = $request('/', $originPort, ['If-Modified-Since: '.$oldHtml['responseHeaders']['last-modified']]);
        expect($revalidated['status'])->toBe(200)->and($revalidated['body'])->toBe($newFiles['index.html']);
        $broken = optimizedDeliveryFiles('red');
        $broken['static-build-manifest.json'] = '{invalid';
        expect(fn () => installDeliveryFixture($directory, $broken, 'wsr_abcdefghijklmnopqrstuvwxyz56'))->toThrow(ProcessFailedException::class);
        expect($request('/', $originPort)['body'])->toBe($newFiles['index.html']);
        $rowglo = getenv('ROWGLO_STATIC_FIXTURE');
        if (is_string($rowglo) && is_dir($rowglo)) {
            $realFiles = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rowglo, FilesystemIterator::SKIP_DOTS)) as $file) {
                $realFiles[substr($file->getPathname(), strlen($rowglo) + 1)] = file_get_contents($file->getPathname());
            }
            installDeliveryFixture($directory, $realFiles, 'wsr_abcdefghijklmnopqrstuvwxyz78');
            expect($request('/', $originPort)['body'])->toBe($realFiles['index.html']);
            $realManifest = json_decode($realFiles['static-build-manifest.json'], true);
            foreach (array_unique(array_values($realManifest['assets'])) as $uri) {
                $cold = $request($uri, $cachePort);
                $hit = $request($uri, $cachePort);
                expect($cold['status'])->toBe(200)->and($cold['body'])->toBe($realFiles[ltrim($uri, '/')])
                    ->and($cold['responseHeaders']['x-test-cache'])->toBe('MISS')
                    ->and($hit['responseHeaders']['x-test-cache'])->toBe('HIT');
            }
        }
    } finally {
        $server?->stop();
        (new Filesystem)->deleteDirectory($directory);
    }
});

it('validates the actual Rowglo build through the shared receiver contract', function (): void {
    $directory = getenv('ROWGLO_STATIC_FIXTURE');
    if (! is_string($directory) || ! is_dir($directory)) {
        $this->markTestSkipped('Set ROWGLO_STATIC_FIXTURE to a completed Rowglo build.');
    }
    $paths = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        $paths[] = substr($file->getPathname(), strlen($directory) + 1);
    }
    (new StaticArtifactValidator)->validate($paths, fn (string $path): string|false => is_file($directory.'/'.$path) ? file_get_contents($directory.'/'.$path) : false);
    expect(file_get_contents($directory.'/index.html'))->toContain('/assets/static/')->not->toContain('/wp-content/fonts/google/');
});

it('keeps the old public target and permits retry after a publishing failure', function (): void {
    $directory = sys_get_temp_dir().'/sitewell-publish-'.bin2hex(random_bytes(8));
    mkdir($directory);
    try {
        $oldFiles = optimizedDeliveryFiles();
        installDeliveryFixture($directory, $oldFiles, 'wsr_abcdefghijklmnopqrstuvwxyz12');
        $newFiles = optimizedDeliveryFiles('navy');
        $manifest = json_decode($newFiles['static-build-manifest.json'], true);
        $obstruction = $directory.'/public'.$manifest['assets']['/site.css'];
        mkdir($obstruction);
        expect(fn () => installDeliveryFixture($directory, $newFiles, 'wsr_abcdefghijklmnopqrstuvwxyz34'))
            ->toThrow(ProcessFailedException::class);
        expect(file_get_contents($directory.'/public/current/index.html'))->toBe($oldFiles['index.html'])
            ->and(is_dir($directory.'/releases/wsr_abcdefghijklmnopqrstuvwxyz34'))->toBeFalse();
        rmdir($obstruction);
        installDeliveryFixture($directory, $newFiles, 'wsr_abcdefghijklmnopqrstuvwxyz34');
        clearstatcache(true);
        expect(file_get_contents($directory.'/public/current/index.html'))->toBe($newFiles['index.html']);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }
});

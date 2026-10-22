<?php

use App\Services\PhpSecurityChecker;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
});

function securityCheckerFixture(array $responses = [], array $addresses = ['93.184.216.34']): PhpSecurityChecker
{
    return new class($responses, $addresses) extends PhpSecurityChecker
    {
        public array $requests = [];

        public function __construct(private array $responses, private array $resolvedAddresses) {}

        protected function addresses(string $host): array
        {
            return $host === 'internal.example' ? ['127.0.0.1'] : $this->resolvedAddresses;
        }

        protected function request(string $url, string $host, string $address): array
        {
            $this->requests[] = [$url, $host, $address];

            return array_shift($this->responses) ?? throw new RuntimeException('Timed out.');
        }
    };
}

it('renders the public form without asking for email', function (): void {
    $this->withoutVite();
    $this->get(route('marketing.php-security-check'))->assertSuccessful()
        ->assertSee('Check my website')->assertDontSee('name="email"', false);
});

it('uses cautious public results', function (array $headers, string $body, string $status): void {
    $checker = securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', ...$headers], 'body' => $body]]);
    expect($checker->check('https://example.com')['status'])->toBe($status);
    expect($checker->requests)->toBe([['https://example.com', 'example.com', '93.184.216.34']]);
})->with([
    'PHP header' => [['x-powered-by' => 'PHP/8.4.27'], '<html></html>', 'potential'],
    'PHP cookie' => [['set-cookie' => 'PHPSESSID=abc; secure'], '', 'potential'],
    'WordPress asset' => [[], '<link href="/wp-content/themes/site/style.css">', 'potential'],
    'WordPress generator' => [[], '<meta name="generator" content="WordPress 7.1">', 'potential'],
    'WordPress API' => [['link' => '<https://example.com/wp-json/>; rel="https://api.w.org/"'], '', 'potential'],
    'no clues' => [[], '<html>A plain page</html>', 'unknown'],
    'article mentions PHP' => [[], '<p>We write about PHP and WordPress.</p>', 'unknown'],
]);

it('blocks non-public and special addresses', function (string $ip): void {
    expect((new PhpSecurityChecker)->isPublicAddress($ip))->toBeFalse();
    $checker = securityCheckerFixture([], [$ip]);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toBeEmpty();
})->with(['127.0.0.1', '10.0.0.1', '172.16.1.1', '192.168.1.1', '169.254.169.254', '100.100.100.200', '0.0.0.0', '224.0.0.1', '::1', 'fc00::1', 'fe80::1', '::ffff:127.0.0.1', '2002:7f00:1::', '2001:db8::1']);

it('rejects unsafe URL forms before connecting', function (string $url): void {
    $checker = securityCheckerFixture();
    expect($checker->check($url)['status'])->toBe('unavailable');
    expect($checker->requests)->toBeEmpty();
})->with(['file:///etc/passwd', 'http://localhost', 'http://127.0.0.1', 'http://2130706433', 'https://example.com:8080', 'https://user:pass@example.com', 'http://[::1]/', 'http://example.com\\@127.0.0.1/']);

it('rejects mixed public and private DNS answers', function (): void {
    $checker = securityCheckerFixture([], ['93.184.216.34', '10.0.0.1']);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toBeEmpty();
});

it('revalidates redirected destinations', function (): void {
    $checker = securityCheckerFixture([['status' => 302, 'headers' => ['location' => 'http://internal.example/'], 'body' => '']]);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toHaveCount(1);
});

it('follows relative redirects and bounds redirect loops', function (): void {
    $redirect = ['status' => 302, 'headers' => ['location' => '/next'], 'body' => ''];
    $checker = securityCheckerFixture([$redirect, ['status' => 200, 'headers' => ['content-type' => 'text/html'], 'body' => '']]);
    expect($checker->check('https://example.com')['url'])->toBe('https://example.com/next');
    $checker = securityCheckerFixture(array_fill(0, 5, $redirect));
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toHaveCount(4);
});

it('does not treat blocked or non-HTML pages as a clean result', function (int $status, string $type): void {
    $checker = securityCheckerFixture([['status' => $status, 'headers' => ['content-type' => $type], 'body' => '']]);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
})->with([[403, 'text/html'], [503, 'text/html'], [200, 'application/json']]);

it('handles connection failure honestly', function (): void {
    expect(securityCheckerFixture()->check('https://example.com')['status'])->toBe('unavailable');
});

it('normalises bare domains and renders results before the help action', function (): void {
    $this->withoutVite();
    $this->instance(PhpSecurityChecker::class, securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', 'x-powered-by' => 'PHP/8.4.27'], 'body' => '']]));
    $response = $this->post(route('marketing.php-security-check.store'), ['url' => 'example.com']);
    $response->assertSuccessful()->assertSeeInOrder(['Potentially affected', 'Ask Ross to help'])
        ->assertSee('does not confirm a vulnerability')->assertSee('backport')->assertDontSee('name="email"', false);
});

it('validates input and the honeypot', function (): void {
    $this->post(route('marketing.php-security-check.store'), ['url' => 'file:///etc/passwd'])->assertSessionHasErrors('url');
    $this->post(route('marketing.php-security-check.store'), ['url' => 'https://example.com', '_sitewell_check' => 'bot'])->assertSessionHasErrors('_sitewell_check');
});

it('rate limits visitors before fetching', function (): void {
    $checker = securityCheckerFixture();
    $this->instance(PhpSecurityChecker::class, $checker);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('marketing.php-security-check.store'), ['url' => 'file:///etc/passwd'])->assertRedirect();
    }
    $this->post(route('marketing.php-security-check.store'), ['url' => 'https://example.com'])->assertTooManyRequests();
    expect($checker->requests)->toBeEmpty();
});

it('rate limits the target across visitors', function (): void {
    $key = 'php-security-domain:'.hash('sha256', 'example.com');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        RateLimiter::hit($key, 60);
    }
    $this->post(route('marketing.php-security-check.store'), ['url' => 'https://example.com'])->assertTooManyRequests();
});

it('enforces response limits in the real curl transport', function (string $response, bool $fails): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Requires pcntl for an isolated HTTP fixture.');
    }
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    $child = pcntl_fork();
    if ($child === 0) {
        $client = stream_socket_accept($server, 5);
        if ($client) {
            fread($client, 8192);
            @fwrite($client, $response);
            fclose($client);
        }
        fclose($server);
        exit(0);
    }
    fclose($server);
    $checker = new class extends PhpSecurityChecker
    {
        public function transport(string $url): array
        {
            return $this->request($url, 'fixture.example', '127.0.0.1');
        }
    };
    try {
        if ($fails) {
            expect(fn () => $checker->transport('http://127.0.0.1:'.$port))->toThrow(RuntimeException::class);
        } else {
            expect($checker->transport('http://127.0.0.1:'.$port)['body'])->toBe('hello');
        }
    } finally {
        pcntl_waitpid($child, $status);
    }
})->with([
    'small page' => ["HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: 5\r\nConnection: close\r\n\r\nhello", false],
    'oversized body' => ["HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: close\r\n\r\n".str_repeat('a', 524289), true],
    'oversized headers' => ["HTTP/1.1 200 OK\r\nX-Large: ".str_repeat('a', 32769)."\r\n\r\n", true],
]);

it('renders uncertainty without a safe verdict', function (string $status, string $message): void {
    $this->withoutVite();
    $this->mock(PhpSecurityChecker::class)->shouldReceive('check')->once()->andReturn(['status' => $status, 'evidence' => [], 'url' => 'https://example.com']);
    $this->post(route('marketing.php-security-check.store'), ['url' => 'https://example.com'])
        ->assertSuccessful()->assertSee($message)->assertSee('Ask Ross to help')->assertDontSee('Your website is safe');
})->with([
    ['unknown', 'Unable to confirm PHP'],
    ['unavailable', 'We couldn’t check this website'],
]);

it('rejects missing DNS answers and permits public IPv6', function (): void {
    $checker = securityCheckerFixture([], []);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toBeEmpty();
    expect($checker->isPublicAddress('2606:4700:4700::1111'))->toBeTrue();
});

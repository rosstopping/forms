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
        ->assertSee('Is your website affected?')
        ->assertSee('Enter your website below to check whether it could be affected by the latest security issue.')
        ->assertSee('Check my website')->assertDontSee('name="email"', false)
        ->assertDontSee('security-url-help')->assertDontSee('attack attempts');
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
    $response->assertSuccessful()->assertSeeInOrder(['Your website could be affected', 'Ask Ross for help'])
        ->assertSee('does not mean your website is vulnerable')->assertSee('Your hosting provider may already have fixed it')->assertDontSee('name="email"', false);
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
        ->assertSuccessful()->assertSee($message)->assertSee('Ask Ross for help')->assertDontSee('Your website is safe');
})->with([
    ['unknown', 'We can’t tell from this check'],
    ['unavailable', 'We couldn’t check your website'],
]);

it('rejects missing DNS answers and permits public IPv6', function (): void {
    $checker = securityCheckerFixture([], []);
    expect($checker->check('https://example.com')['status'])->toBe('unavailable');
    expect($checker->requests)->toBeEmpty();
    expect($checker->isPublicAddress('2606:4700:4700::1111'))->toBeTrue();
});

it('recognises specific PHP platform fingerprints without fetching more pages', function (array $headers, string $html, string $platform): void {
    $checker = securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', ...$headers], 'body' => $html]]);
    $result = $checker->check('https://example.com');
    expect($result['status'])->toBe('potential');
    expect(implode(' ', $result['evidence']))->toContain($platform);
    expect($checker->requests)->toHaveCount(1);
})->with([
    'Laravel default underscore cookie' => [['set-cookie' => 'laravel_session=abc; HttpOnly'], '', 'Laravel'],
    'Laravel default hyphen cookie' => [['set-cookie' => 'laravel-session=abc; HttpOnly'], '', 'Laravel'],
    'Drupal response header' => [['x-generator' => 'Drupal 11 (https://www.drupal.org)'], '', 'Drupal'],
    'Drupal generator' => [[], '<meta content="Drupal 11 (https://www.drupal.org)" name="Generator">', 'Drupal'],
    'Joomla generator' => [[], '<meta name="generator" content="Joomla! - Open Source Content Management">', 'Joomla'],
    'TYPO3 generator' => [[], "<meta content='TYPO3 CMS' name='generator'>", 'TYPO3'],
    'Magento cookie with expiry' => [['set-cookie' => 'other=1; Expires=Wed, 28 Oct 2026 10:00:00 GMT; Path=/, X-Magento-Vary=abc123; Secure'], '', 'Magento'],
    'Craft header' => [['x-powered-by' => 'Craft CMS'], '', 'Craft CMS'],
    'Symfony matching profiler headers' => [['x-debug-token' => '110e1e', 'x-debug-token-link' => 'https://example.com/_profiler/110e1e'], '', 'Symfony'],
]);

it('recognises renamed Laravel sessions only with corroborating encrypted-cookie evidence', function (): void {
    $cookie = rawurlencode(encrypt('test-session'));
    $checker = securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', 'set-cookie' => 'XSRF-TOKEN=token; Path=/, the_amp_camp_session='.$cookie.'; HttpOnly'], 'body' => '<meta name="csrf-token" content="test-token">']]);
    $result = $checker->check('https://example.com');
    expect($result['status'])->toBe('potential');
    expect(implode(' ', $result['evidence']))->toContain('Laravel');
});

it('does not infer PHP from generic or misleading platform mentions', function (array $headers, string $html): void {
    $checker = securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', ...$headers], 'body' => $html]]);
    expect($checker->check('https://example.com')['status'])->toBe('unknown');
})->with([
    'framework names in article' => [[], '<p>Laravel, Symfony, Drupal, Joomla, Magento, TYPO3 and Craft CMS tutorials.</p>'],
    'generic CSRF meta' => [[], '<meta name="csrf-token" content="token">'],
    'generic XSRF cookie' => [['set-cookie' => 'XSRF-TOKEN=abc; Path=/'], ''],
    'generic session cookie' => [['set-cookie' => 'custom_session=abc; HttpOnly'], ''],
    'custom session plus XSRF and CSRF without Laravel encryption' => [['set-cookie' => 'XSRF-TOKEN=abc; Path=/, custom_session=abc; HttpOnly'], '<meta name="csrf-token" content="token">'],
    'Laravel cookie name inside cookie value' => [['set-cookie' => 'analytics=laravel_session=abc; Path=/'], ''],
    'Laravel cookie suffix' => [['set-cookie' => 'not_laravel_session=abc; Path=/'], ''],
    'Drupal in description' => [[], '<meta name="description" content="Drupal 11 development services">'],
    'generator tutorial name' => [[], '<meta name="generator" content="Drupal tutorial site builder">'],
    'escaped generator example' => [[], '&lt;meta name="generator" content="Joomla! - Open Source Content Management"&gt;'],
    'commented generator' => [[], '<!-- <meta name="generator" content="TYPO3 CMS"> -->'],
    'JS string generator example' => [[], '<script>const example = \'<meta name="generator" content="Drupal 11">\';</script>'],
    'Craft name prefix' => [['x-powered-by' => 'Craft CMS-like site builder'], ''],
    'Magento cookie text inside other value' => [['set-cookie' => 'analytics=X-Magento-Vary=abc; Path=/'], ''],
    'Symfony token only' => [['x-debug-token' => '110e1e'], ''],
    'Symfony mismatched token' => [['x-debug-token' => '110e1e', 'x-debug-token-link' => 'https://example.com/_profiler/aaaaaa'], ''],
    'JavaScript frontend' => [[], '<meta name="generator" content="Next.js"><script src="/_nuxt/app.js"></script>'],
]);

it('keeps the visitor result simple for detected PHP platforms', function (): void {
    $this->withoutVite();
    $this->instance(PhpSecurityChecker::class, securityCheckerFixture([['status' => 200, 'headers' => ['content-type' => 'text/html', 'set-cookie' => 'laravel_session=abc'], 'body' => '']]));
    $this->post(route('marketing.php-security-check.store'), ['url' => 'https://example.com'])
        ->assertSuccessful()->assertSee('Your website could be affected')
        ->assertSee('does not mean your website is vulnerable')
        ->assertDontSee('Laravel indicators')->assertDontSee('What we found');
});

it('links the public security banner directly to the checker', function (string $route): void {
    $this->withoutVite();
    $response = $this->get(route($route))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $banner = $xpath->query('//aside[@aria-label="Website security notice"]');
    expect($banner->length)->toBe(1);
    expect($banner->item(0)->textContent)->toContain('critical PHP security update');
    $link = $xpath->query('//aside[@aria-label="Website security notice"]//a')->item(0);
    expect($link->getAttribute('href'))->toBe(route('marketing.php-security-check'));
    expect(trim($link->textContent))->toBe('Check your website');
})->with(['marketing.home', 'marketing.contact', 'marketing.php-security-check']);

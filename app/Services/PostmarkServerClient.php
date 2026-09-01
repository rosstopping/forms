<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Throwable;

class PostmarkServerClient
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function send(string $serverToken, array $message): array
    {
        try {
            return $this->http
                ->baseUrl((string) config('services.postmark.api_url'))
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Postmark-Server-Token' => $serverToken,
                ])
                ->timeout(10)
                ->post('/email', $message)
                ->throw()
                ->json();
        } catch (Throwable) {
            throw new RuntimeException('Postmark could not send the email.');
        }
    }
}

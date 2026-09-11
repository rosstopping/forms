<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;

class PostmarkAccountClient
{
    public function __construct(private HttpFactory $http) {}

    /** @return array<string, mixed> */
    public function findOrCreateDomain(string $domain): array
    {
        $response = $this->request()->get('/domains', ['count' => 500, 'offset' => 0])->throw();
        $existingDomain = collect($response->json('Domains', []))->first(
            fn (array $candidate): bool => strcasecmp((string) ($candidate['Name'] ?? ''), $domain) === 0,
        );

        if ($existingDomain) {
            return $this->domain((string) $existingDomain['ID']);
        }

        return $this->request()->post('/domains', [
            'Name' => $domain,
            'ReturnPathDomain' => 'pm-bounces.'.$domain,
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function domain(string $domainId): array
    {
        return $this->request()->get('/domains/'.$domainId)->throw()->json();
    }

    /** @return array<string, mixed> */
    public function findOrCreateServer(string $name): array
    {
        $response = $this->request()->get('/servers', ['count' => 500, 'offset' => 0, 'name' => $name])->throw();
        $existingServer = collect($response->json('Servers', []))->first(
            fn (array $candidate): bool => ($candidate['Name'] ?? null) === $name,
        );

        if ($existingServer) {
            return $this->request()->get('/servers/'.$existingServer['ID'])->throw()->json();
        }

        return $this->request()->post('/servers', [
            'Name' => $name,
            'Color' => 'Turquoise',
            'SmtpApiActivated' => false,
            'RawEmailEnabled' => false,
        ])->throw()->json();
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl((string) config('services.postmark.api_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Postmark-Account-Token' => (string) config('services.postmark.account_token')])
            ->timeout(10);
    }
}

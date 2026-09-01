<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteMailConnection;
use Illuminate\Support\Str;
use RuntimeException;

class ManagedPostmarkProvisioner
{
    public function __construct(private PostmarkAccountClient $postmark) {}

    public function provision(Website $website, string $domain): WebsiteMailConnection
    {
        if (blank(config('services.postmark.account_token'))) {
            throw new RuntimeException('Managed Postmark has not been configured.');
        }

        $domainDetails = $this->postmark->findOrCreateDomain($domain);
        $server = $this->postmark->findOrCreateServer($this->serverName($website));
        $serverToken = data_get($server, 'ApiTokens.0');

        if (blank($serverToken)) {
            throw new RuntimeException('Postmark did not return a Server API token.');
        }

        $connection = $website->mailConnection()->firstOrNew();
        $connection->fill([
            'mode' => WebsiteMailConnection::MODE_MANAGED,
            'status' => ($domainDetails['DKIMVerified'] ?? false) ? 'active' : 'pending_verification',
            'postmark_server_token' => $serverToken,
            'postmark_server_id' => (string) $server['ID'],
            'postmark_domain_id' => (string) $domainDetails['ID'],
            'sending_domain' => $domain,
            'connected_at' => $connection->connected_at ?? now(),
            'paused_at' => null,
            'pause_reason' => null,
        ]);
        $this->fillDomainDetails($connection, $domainDetails);
        $connection->save();

        return $connection;
    }

    public function refresh(WebsiteMailConnection $connection): WebsiteMailConnection
    {
        if ($connection->mode !== WebsiteMailConnection::MODE_MANAGED || blank($connection->postmark_domain_id)) {
            throw new RuntimeException('This website does not have a managed Postmark domain.');
        }

        $domainDetails = $this->postmark->domain($connection->postmark_domain_id);
        $this->fillDomainDetails($connection, $domainDetails);
        $connection->status = $connection->dkim_verified ? 'active' : 'pending_verification';
        $connection->save();

        return $connection;
    }

    /** @param array<string, mixed> $details */
    private function fillDomainDetails(WebsiteMailConnection $connection, array $details): void
    {
        $connection->fill([
            'dkim_host' => ($details['DKIMPendingHost'] ?? null) ?: ($details['DKIMHost'] ?? null),
            'dkim_value' => ($details['DKIMPendingTextValue'] ?? null) ?: ($details['DKIMTextValue'] ?? null),
            'dkim_verified' => (bool) ($details['DKIMVerified'] ?? false),
            'return_path_domain' => $details['ReturnPathDomain'] ?? null,
            'return_path_cname_value' => $details['ReturnPathDomainCNAMEValue'] ?? null,
            'return_path_verified' => (bool) ($details['ReturnPathDomainVerified'] ?? false),
            'verification_checked_at' => now(),
        ]);
    }

    private function serverName(Website $website): string
    {
        return Str::limit('Sitewell '.$website->id.' '.$website->name, 100, '');
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class NetlifyClient
{
    /** @param array<string, string> $files
     * @return array{id: string, url: string, domain: string}
     */
    public function deploy(array $files): array
    {
        $token = (string) config('services.netlify.token');

        if ($token === '') {
            throw new RuntimeException('Netlify is not configured. Add a NETLIFY_ACCESS_TOKEN first.');
        }

        $archivePath = tempnam(sys_get_temp_dir(), 'sitewell-netlify-');

        if ($archivePath === false) {
            throw new RuntimeException('The website archive could not be created.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($archivePath, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('The website archive could not be opened.');
            }

            foreach ($files as $path => $contents) {
                $archive->addFromString($path, $contents);
            }

            $archive->close();
            $response = Http::baseUrl((string) config('services.netlify.api_url'))
                ->withToken($token)
                ->connectTimeout(5)
                ->timeout(45)
                ->retry([250, 750], throw: false)
                ->withBody((string) file_get_contents($archivePath), 'application/zip')
                ->post('sites')
                ->throw();
        } finally {
            @unlink($archivePath);
        }

        $url = $response->json('ssl_url') ?: $response->json('url');
        $id = $response->json('id');
        $domain = $response->json('default_domain') ?: parse_url((string) $url, PHP_URL_HOST);

        if (! is_string($id) || ! is_string($url) || ! is_string($domain)) {
            throw new RuntimeException('Netlify did not return the new website details.');
        }

        return ['id' => $id, 'url' => $url, 'domain' => $domain];
    }
}

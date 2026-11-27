<?php

namespace App\Services;

use App\Models\ReportingSnapshot;
use App\Models\Website;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleReportingSnapshots
{
    public function __construct(private GoogleOAuthClient $oauth) {}

    public function analytics(Website $site, string $property): void
    {
        if (! ctype_digit($property) || $property === '0') {
            throw new RuntimeException('Invalid GA4 property ID.');
        }
        $end = today()->startOfWeek(Carbon::MONDAY)->subDay();
        $start = $end->copy()->subDays(6);
        $values = $this->google($site)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport", [
            'dateRanges' => [['startDate' => $start->toDateString(), 'endDate' => $end->toDateString()]],
            'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers']],
            'keepEmptyRows' => true,
        ])->throw()->json('rows.0.metricValues');
        $sessions = data_get($values, '0.value');
        $users = data_get($values, '1.value');
        if (! ctype_digit((string) $sessions) || ! ctype_digit((string) $users)) {
            throw new RuntimeException('GA4 did not return both totals.');
        }
        $this->save($site, 'analytics', $property, $start, $end, ['sessions' => (int) $sessions, 'active_users' => (int) $users]);
    }

    public function indexing(Website $site, string $property): void
    {
        $home = str_starts_with($property, 'sc-domain:')
            ? 'https://'.($site->primaryDomain()?->domain ?: substr($property, 10)).'/'
            : $property;
        $report = $site->healthReports()->where('status', 'completed')->latest('completed_at')->first();
        $pages = $report?->pages()->orderBy('depth')->orderBy('id')->limit(20)->pluck('url') ?? collect();
        $urls = $pages->prepend($home)->unique()->filter(function (string $url) use ($property): bool {
            if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return false;
            }
            if (! str_starts_with($property, 'sc-domain:')) {
                return parse_url($url, PHP_URL_HOST) === parse_url($property, PHP_URL_HOST) && str_starts_with($url, $property);
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $domain = strtolower(substr($property, 10));

            return $host === $domain || str_ends_with($host, '.'.$domain);
        })->take(5);
        if ($urls->isEmpty()) {
            throw new RuntimeException('No URLs matched the Search Console property.');
        }
        $google = $this->google($site);
        $results = [];
        foreach ($urls as $url) {
            $check = $google->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $url, 'siteUrl' => $property, 'languageCode' => 'en-GB',
            ])->throw()->json('inspectionResult.indexStatusResult');
            if (! is_array($check) || ! isset($check['verdict'])) {
                throw new RuntimeException('Google did not return an index verdict.');
            }
            $results[] = ['url' => $url, 'verdict' => $check['verdict'], 'coverage' => $check['coverageState'] ?? null];
        }
        $this->save($site, 'indexing', $property, today(), today(), ['urls' => $results, 'indexed' => collect($results)->where('verdict', 'PASS')->count(), 'checked' => count($results)]);
    }

    private function google(Website $site): PendingRequest
    {
        return Http::acceptJson()->withToken($this->oauth->accessToken($site->searchConsoleConnection))
            ->withoutRedirecting()->connectTimeout(4)->timeout(12);
    }

    private function save(Website $site, string $source, string $property, Carbon $start, Carbon $end, array $metrics): void
    {
        ReportingSnapshot::upsert([[
            'website_id' => $site->id, 'source' => $source, 'property_hash' => hash('sha256', $property), 'period_end' => $end->toDateString(),
            'property' => $property, 'period_start' => $start->toDateString(), 'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
        ]], ['website_id', 'source', 'property_hash', 'period_end'], ['metrics', 'updated_at']);
    }
}

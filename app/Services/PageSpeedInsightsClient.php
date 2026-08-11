<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PageSpeedInsightsClient
{
    protected const ENDPOINT = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * @param  array<int, string>  $urls
     * @return array{pages: array<int, array<string, mixed>>, checks: array<int, array<string, mixed>>}
     */
    public function audit(array $urls): array
    {
        $targets = collect($urls)
            ->filter(fn (string $url): bool => Str::startsWith($url, ['https://', 'http://']))
            ->unique()
            ->take((int) config('forms.pagespeed.max_pages'))
            ->flatMap(fn (string $url): array => [
                $this->targetKey($url, 'mobile') => ['url' => $url, 'strategy' => 'mobile'],
                $this->targetKey($url, 'desktop') => ['url' => $url, 'strategy' => 'desktop'],
            ]);

        if ($targets->isEmpty()) {
            return ['pages' => [], 'checks' => []];
        }

        $responses = Http::pool(fn (Pool $pool): array => $targets
            ->mapWithKeys(fn (array $target, string $key): array => [
                $key => $pool->as($key)
                    ->acceptJson()
                    ->connectTimeout((int) config('forms.pagespeed.connect_timeout'))
                    ->timeout((int) config('forms.pagespeed.timeout'))
                    ->get(self::ENDPOINT, array_filter([
                        'url' => $target['url'],
                        'strategy' => $target['strategy'],
                        'category' => 'performance',
                        'key' => config('forms.pagespeed.api_key'),
                    ])),
            ])->all());

        $pages = $targets->map(function (array $target, string $key) use ($responses): array {
            $response = $responses[$key] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                return [...$target, 'available' => false];
            }

            return [...$target, ...$this->result($response->json())];
        })->values()->all();

        return [
            'pages' => $pages,
            'checks' => collect($pages)->flatMap(function (array $page): array {
                if (! $page['available']) {
                    return [];
                }

                $checks = [$this->performanceCheck($page)];

                if (data_get($page, 'field.available')) {
                    $checks[] = $this->coreWebVitalsCheck($page);
                }

                return $checks;
            })->all(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function result(array $data): array
    {
        $audits = data_get($data, 'lighthouseResult.audits', []);
        $fieldMetrics = data_get($data, 'loadingExperience.metrics') ?: data_get($data, 'originLoadingExperience.metrics', []);

        return [
            'available' => true,
            'analyzed_at' => data_get($data, 'lighthouseResult.fetchTime'),
            'final_url' => data_get($data, 'lighthouseResult.finalUrl'),
            'performance_score' => (int) round(((float) data_get($data, 'lighthouseResult.categories.performance.score', 0)) * 100),
            'lab' => [
                'lcp_ms' => $this->numericAudit($audits, 'largest-contentful-paint'),
                'cls' => $this->numericAudit($audits, 'cumulative-layout-shift'),
                'tbt_ms' => $this->numericAudit($audits, 'total-blocking-time'),
                'fcp_ms' => $this->numericAudit($audits, 'first-contentful-paint'),
                'speed_index_ms' => $this->numericAudit($audits, 'speed-index'),
            ],
            'field' => $this->fieldMetrics(is_array($fieldMetrics) ? $fieldMetrics : []),
            'recommendations' => $this->recommendations(is_array($audits) ? $audits : []),
        ];
    }

    /** @param array<string, mixed> $audits */
    protected function numericAudit(array $audits, string $key): ?float
    {
        $value = data_get($audits, $key.'.numericValue');

        return is_numeric($value) ? round((float) $value, 3) : null;
    }

    /** @param array<string, mixed> $metrics @return array<string, mixed> */
    protected function fieldMetrics(array $metrics): array
    {
        $lcp = data_get($metrics, 'LARGEST_CONTENTFUL_PAINT_MS.percentile');
        $inp = data_get($metrics, 'INTERACTION_TO_NEXT_PAINT.percentile');
        $cls = data_get($metrics, 'CUMULATIVE_LAYOUT_SHIFT_SCORE.percentile');

        return [
            'available' => is_numeric($lcp) || is_numeric($inp) || is_numeric($cls),
            'lcp_ms' => is_numeric($lcp) ? (int) $lcp : null,
            'inp_ms' => is_numeric($inp) ? (int) $inp : null,
            'cls' => is_numeric($cls) ? round(((float) $cls) / 100, 3) : null,
            'lcp_status' => $this->metricStatus($lcp, 2500, 4000),
            'inp_status' => $this->metricStatus($inp, 200, 500),
            'cls_status' => $this->metricStatus(is_numeric($cls) ? ((float) $cls) / 100 : null, 0.1, 0.25),
        ];
    }

    /** @param array<string, mixed> $audits @return array<int, array<string, mixed>> */
    protected function recommendations(array $audits): array
    {
        return collect($audits)
            ->filter(fn (array $audit): bool => is_numeric($audit['score'] ?? null) && $audit['score'] < 0.9 && in_array($audit['scoreDisplayMode'] ?? null, ['numeric', 'binary'], true))
            ->sortBy('score')
            ->take(5)
            ->map(fn (array $audit): array => [
                'key' => (string) ($audit['id'] ?? ''),
                'title' => Str::limit((string) ($audit['title'] ?? 'Performance opportunity'), 160),
                'description' => Str::limit(strip_tags((string) ($audit['description'] ?? '')), 500),
                'score' => round((float) ($audit['score'] ?? 0), 2),
                'savings_ms' => (int) round((float) data_get($audit, 'details.overallSavingsMs', 0)),
                'savings_bytes' => (int) round((float) data_get($audit, 'details.overallSavingsBytes', 0)),
            ])->values()->all();
    }

    /** @param array<string, mixed> $page @return array<string, mixed> */
    protected function performanceCheck(array $page): array
    {
        $label = ucfirst($page['strategy']).' PageSpeed performance';

        $score = (int) $page['performance_score'];
        $status = $score >= 90 ? 'passed' : ($score >= 50 ? 'warning' : 'failed');

        return $this->healthCheck($page, $label, $status, "The Lighthouse performance score was {$score}/100.");
    }

    /** @param array<string, mixed> $page @return array<string, mixed> */
    protected function coreWebVitalsCheck(array $page): array
    {
        $statuses = collect(['lcp_status', 'inp_status', 'cls_status'])
            ->map(fn (string $metric): ?string => data_get($page, 'field.'.$metric))
            ->filter();
        $status = $statuses->contains('poor') ? 'failed' : ($statuses->contains('needs_improvement') ? 'warning' : 'passed');
        $field = $page['field'];
        $message = sprintf(
            'Real-user 75th percentile: LCP %s, INP %s, and CLS %s.',
            $field['lcp_ms'] === null ? 'unavailable' : number_format($field['lcp_ms']).' ms',
            $field['inp_ms'] === null ? 'unavailable' : number_format($field['inp_ms']).' ms',
            $field['cls'] === null ? 'unavailable' : number_format($field['cls'], 2),
        );

        return [
            ...$this->healthCheck($page, ucfirst($page['strategy']).' Core Web Vitals', $status, $message),
            'key' => 'core_web_vitals_'.$page['strategy'].'_'.hash('sha256', $page['url']),
        ];
    }

    /** @param array<string, mixed> $page @return array<string, mixed> */
    protected function healthCheck(array $page, string $label, string $status, string $message): array
    {
        return [
            'category' => 'performance',
            'key' => 'pagespeed_'.$page['strategy'].'_'.hash('sha256', $page['url']),
            'label' => $label,
            'status' => $status,
            'message' => $message.' Page: '.$page['url'],
            'details' => ['url' => $page['url'], 'strategy' => $page['strategy']],
        ];
    }

    protected function metricStatus(mixed $value, float $goodMaximum, float $poorAbove): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return $value <= $goodMaximum ? 'good' : ($value > $poorAbove ? 'poor' : 'needs_improvement');
    }

    protected function targetKey(string $url, string $strategy): string
    {
        return $strategy.'_'.hash('sha256', $url);
    }
}

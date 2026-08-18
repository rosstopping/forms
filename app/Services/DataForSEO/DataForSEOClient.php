<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DataForSEOClient
{
    public function get(string $endpoint): DataForSEOResponse
    {
        $this->ensureConfigured();

        try {
            $response = $this->request()->get($endpoint);
        } catch (Throwable $exception) {
            $this->logFailure($endpoint, null, null, null, $exception);

            throw new DataForSEOException('DataForSEO could not be reached.', $endpoint, previous: $exception);
        }

        if ($response->failed()) {
            $this->logFailure($endpoint, $response->status());

            throw new DataForSEOException(
                $response->status() === 429 ? 'DataForSEO rate limit reached.' : 'DataForSEO request failed.',
                $endpoint,
                $response->status(),
            );
        }

        return $this->validatedResponse($endpoint, $response);
    }

    /** @param array<string, mixed> $task */
    public function post(string $endpoint, array $task): DataForSEOResponse
    {
        $this->ensureConfigured();

        try {
            $response = $this->request()->post($endpoint, [$task]);
        } catch (Throwable $exception) {
            $this->logFailure($endpoint, null, null, null, $exception);

            throw new DataForSEOException('DataForSEO could not be reached.', $endpoint, previous: $exception);
        }

        if ($response->failed()) {
            $this->logFailure($endpoint, $response->status());

            throw new DataForSEOException(
                $response->status() === 429 ? 'DataForSEO rate limit reached.' : 'DataForSEO request failed.',
                $endpoint,
                $response->status(),
            );
        }

        return $this->validatedResponse($endpoint, $response);
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.dataforseo.api_url'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth((string) config('services.dataforseo.login'), (string) config('services.dataforseo.password'))
            ->connectTimeout((int) config('services.dataforseo.connect_timeout'))
            ->timeout((int) config('services.dataforseo.timeout'))
            ->retry([250, 1000], function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
            }, throw: false);
    }

    protected function validatedResponse(string $endpoint, Response $response): DataForSEOResponse
    {
        $body = $response->json();
        $task = is_array($body) ? data_get($body, 'tasks.0') : null;
        $providerStatus = is_array($body) && is_numeric($body['status_code'] ?? null) ? (int) $body['status_code'] : null;
        $taskStatus = is_array($task) && is_numeric($task['status_code'] ?? null) ? (int) $task['status_code'] : null;
        $providerMessage = is_array($task) && is_string($task['status_message'] ?? null)
            ? $task['status_message']
            : (is_array($body) && is_string($body['status_message'] ?? null) ? $body['status_message'] : null);

        if (! is_array($body) || $providerStatus !== 20000 || ! is_array($task) || $taskStatus !== 20000 || ! is_array($task['result'] ?? null)) {
            $this->logFailure($endpoint, $response->status(), $taskStatus ?? $providerStatus, $providerMessage);

            $status = $taskStatus ?? $providerStatus;
            $detail = collect([$status, $providerMessage])->filter(fn (mixed $value): bool => filled($value))->implode(': ');

            throw new DataForSEOException('DataForSEO rejected the request'.($detail ? " ({$detail})." : '.'), $endpoint, $status);
        }

        return new DataForSEOResponse(
            endpoint: $endpoint,
            results: $task['result'],
            cost: (float) ($task['cost'] ?? $body['cost'] ?? 0),
            resultCount: (int) ($task['result_count'] ?? count($task['result'])),
            taskId: is_string($task['id'] ?? null) ? $task['id'] : null,
        );
    }

    protected function ensureConfigured(): void
    {
        if (! config('services.dataforseo.login') || ! config('services.dataforseo.password')) {
            throw new DataForSEOException('DataForSEO is not configured.');
        }
    }

    protected function logFailure(string $endpoint, ?int $httpStatus = null, ?int $providerStatus = null, ?string $providerMessage = null, ?Throwable $exception = null): void
    {
        Log::warning('DataForSEO request failed.', array_filter([
            'provider' => 'dataforseo',
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'provider_status_code' => $providerStatus,
            'provider_status_message' => $providerMessage,
            'exception' => $exception ? $exception::class : null,
        ], fn (mixed $value): bool => $value !== null));
    }
}
